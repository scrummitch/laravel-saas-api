<?php

namespace App\Http\Controllers\API;

use App\Convert\Enums\ElementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreElementRequest;
use App\Http\Requests\UpdateElementRequest;
use App\Http\Resources\ElementApiResource;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Convert\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ElementsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Element::query()
            ->where('organization_id', $request->user()->currentOrganization->id)
            ->with(['handlers', 'handlers.flow'])
            ->latest('id');

        return ElementApiResource::collection($query->simplePaginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreElementRequest $request)
    {
        $validated = $request->validated();

        $element = new Element();
        $element->organization_id = $request->user()->currentOrganization->id;
        $element->display_name = Arr::get($validated, 'display_name');
        $element->type = ElementType::Paywall;
        $element->mode = Arr::get($validated, 'mode') === 'managed' ? 1 : 0;
        $element->lookup_key = Arr::get($validated, 'lookup_key');
        $element->conditions = Arr::get($validated, 'conditions');

        $element->save();

        return new ElementApiResource($element);
    }

    /**
     * Display the specified resource.
     */
    public function show(Element $element)
    {
        return new ElementApiResource($element);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateElementRequest $request, Element $element)
    {
        $validated = $request->validated();

        $element->display_name = $validated['display_name'];
        $element->lookup_key = $validated['lookup_key'];
//        $element->current_state = $validated['current_state'];
//        $element->conditions = $validated['conditions'];

        $element->view = $validated['view'];
        if (isset($validated['template'])) {
            $element->template = $validated['template'];
        }

        $element->save();

        $element->refresh();

        return new ElementApiResource($element);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Element $element)
    {
        //
    }
}
