<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectorConstructRequest;
use App\Http\Requests\Admin\StoreSectorRequest;
use App\Http\Requests\Admin\UpdateSectorCoreConstructRequest;
use App\Models\NcsbElement;
use App\Models\Sector;
use App\Services\SectorConstructService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SectorConstructController extends Controller
{
    public function index(?Sector $sector = null): View
    {
        Gate::authorize('manage', Sector::class);

        $sectors = Sector::query()->orderBy('name')->get();
        $sector ??= $sectors->first();

        return view('admin.constructs.index', [
            'sectors' => $sectors,
            'sector' => $sector,
            'core' => $sector ? NcsbElement::query()->orderBy('sort_order')->get() : [],
            'overrides' => $sector?->coreConstructs()->get()->keyBy('ncsb_element_id') ?? collect(),
            'constructs' => $sector?->constructs()->orderBy('code')->get() ?? collect(),
        ]);
    }

    public function storeSector(StoreSectorRequest $request, SectorConstructService $constructs): RedirectResponse
    {
        $sector = $constructs->createSector($request->validated(), $request->user());

        return redirect()->route('admin.constructs.index', $sector)->with('status', 'Sector added.');
    }

    public function updateCore(
        UpdateSectorCoreConstructRequest $request,
        Sector $sector,
        NcsbElement $element,
        SectorConstructService $constructs,
    ): RedirectResponse {
        $changed = $constructs->setCoreStatus(
            $sector, $element, $request->validated('status'), $request->validated('reason'), $request->user(),
        );

        return redirect()->route('admin.constructs.index', $sector)->with(
            'status',
            $changed ? "{$element->code} applicability updated for {$sector->name}." : "{$element->code} already has that status.",
        );
    }

    public function storeConstruct(
        StoreSectorConstructRequest $request,
        Sector $sector,
        SectorConstructService $constructs,
    ): RedirectResponse {
        $constructs->addConstruct($sector, $request->validated(), $request->user());

        return redirect()->route('admin.constructs.index', $sector)->with('status', 'Sector-specific construct added.');
    }
}
