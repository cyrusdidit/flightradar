<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlaneController extends Controller
{
    public function index(Request $request)
    {
        try {
            $res = Http::timeout(12)
                ->acceptJson()
                ->get('https://opensky-network.org/api/states/all');

            if (!$res->ok()) {
                return response()->json([
                    'time' => time(),
                    'planes' => [],
                    'error' => 'OpenSky request failed',
                    'opensky_status' => $res->status(),
                    'opensky_body_preview' => substr($res->body(), 0, 250),
                ], 502);
            }

            $payload = $res->json();
            if (!is_array($payload)) {
                return response()->json([
                    'time' => time(),
                    'planes' => [],
                    'error' => 'OpenSky returned non-JSON',
                    'opensky_status' => $res->status(),
                    'opensky_body_preview' => substr($res->body(), 0, 250),
                ], 502);
            }

            $time = $payload['time'] ?? time();
            $states = $payload['states'] ?? [];

            $planes = [];
            foreach ($states as $s) {
                $icao24 = $s[0] ?? null;
                $callsign = $s[1] ?? null;
                $longitude = $s[5] ?? null;
                $latitude = $s[6] ?? null;
                $baroAlt = $s[7] ?? null;
                $onGround = $s[8] ?? null;
                $velocity = $s[9] ?? null;
                $heading = $s[10] ?? null;
                $geoAlt = $s[13] ?? null;
                $lastContact = $s[4] ?? null;

                if ($latitude === null || $longitude === null || $icao24 === null) continue;

                $altitudeM = $geoAlt ?? $baroAlt;

                $planes[] = [
                    'icao24' => $icao24,
                    'callsign' => $callsign ? trim($callsign) : null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'on_ground' => (bool) $onGround,
                    'velocity_ms' => $velocity,
                    'heading_deg' => $heading,
                    'altitude_m' => $altitudeM,
                    'last_contact' => $lastContact,
                ];
            }

            return response()->json([
                'time' => $time,
                'planes' => $planes,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'time' => time(),
                'planes' => [],
                'error' => 'Exception while calling OpenSky',
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
