<?php

namespace App\Mcp\Resources;

use App\Models\Garaz\Vehicle;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

#[Description('Full profile of a single vehicle: specs, recent service records, expiring documents.')]
class VehicleProfileResource extends Resource
{
    public function handle(Request $request): Response
    {
        $userId = auth()->id();

        if ($userId === null) {
            return Response::text('Authorization required.');
        }

        $vehicleId = $request->get('id');
        $vehicle = Vehicle::query()
            ->where('user_id', $userId)
            ->find($vehicleId);

        if ($vehicle === null) {
            return Response::text("Vehicle {$vehicleId} not found or not accessible.");
        }

        $sections = [];

        $sections[] = "# {$vehicle->nickname}";
        $sections[] = '- type: '.$vehicle->type?->translation();
        $sections[] = '- make/model: '.trim(($vehicle->make ?? '').' '.($vehicle->model ?? ''));

        if ($vehicle->year_of_manufacture) {
            $sections[] = '- year: '.$vehicle->year_of_manufacture;
        }

        if ($vehicle->current_odometer_km) {
            $sections[] = '- mileage: '.number_format($vehicle->current_odometer_km, 0, ',', ' ').' km';
        }

        $spec = $vehicle->spec();

        if ($spec !== null) {
            $sections[] = "\n## Spec";

            foreach ($spec->getAttributes() as $key => $value) {
                if (in_array($key, ['id', 'vehicle_id', 'created_at', 'updated_at'], true) || $value === null || $value === '') {
                    continue;
                }
                $sections[] = "- {$key}: {$value}";
            }
        }

        $records = $vehicle->serviceRecords()->limit(10)->get();

        if ($records->isNotEmpty()) {
            $sections[] = "\n## Recent service history";

            foreach ($records as $r) {
                $sections[] = '- '.$r->performed_at->format('Y-m-d').
                    ' @ '.($r->mileage_km ? number_format($r->mileage_km, 0, ',', ' ').' km' : '?').
                    ': '.($r->category?->translation() ?? '—').
                    ($r->source ? ' ['.$r->source->translation().']' : '').
                    ($r->shop_name ? ' — '.$r->shop_name : '').
                    ($r->total_eur ? ' — '.number_format((float) $r->total_eur, 2, ',', ' ').' €' : '');
            }
        }

        $documents = $vehicle->documents()->expiringSoon(180)->orWhere(fn ($q) => $q->expired())->get();

        if ($documents->isNotEmpty()) {
            $sections[] = "\n## Documents (expiring or expired)";

            foreach ($documents as $d) {
                $sections[] = '- '.$d->type?->translation().': platnosť do '.($d->expires_at?->format('Y-m-d') ?? '?').' ('.$d->expiryStatus().')';
            }
        }

        return Response::text(implode("\n", $sections));
    }
}
