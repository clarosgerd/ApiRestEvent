<?php

namespace App\Http\Controllers;

use App\Models\AgendaItem;
use App\Http\Requests\StoreAgendaItemRequest;
use App\Http\Requests\UpdateAgendaItemRequest;
use App\Http\Resources\AgendaItemResource;
use App\Http\Resources\AgendaItemCollection;
use App\Filters\AgendaItemFilter;
use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AgendaItemController extends Controller
{
    use AuthorizesEventoScope;

    public function index(Request $request)
    {
        $filter = new AgendaItemFilter();
        $filterItems = $filter->transform($request);
        $agendaItem = AgendaItem::where($filterItems);
        return new AgendaItemCollection($agendaItem->paginate()->appends($request->query()));
    }

    public function store(StoreAgendaItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertCanWriteEvento((int) $data['event_id']);

        $agendaItem = AgendaItem::create($data);

        AdminAuditLogger::log('create', 'agenda_item', $agendaItem->id, (int) $agendaItem->event_id, null, $agendaItem->toArray());

        return response()->json([
            'success'    => true,
            'message'    => 'Ítem de agenda creado correctamente.',
            'agendaItem' => new AgendaItemResource($agendaItem),
        ], 201);
    }

    public function show(AgendaItem $agenda_item)
    {
        return new AgendaItemResource($agenda_item);
    }

    public function update(UpdateAgendaItemRequest $request, AgendaItem $agenda_item): JsonResponse
    {
        $this->assertCanWriteEvento((int) $agenda_item->event_id);

        $before = $agenda_item->toArray();
        $agenda_item->update($request->validated());

        AdminAuditLogger::log('update', 'agenda_item', $agenda_item->id, (int) $agenda_item->event_id, $before, $agenda_item->toArray());

        return response()->json([
            'success'    => true,
            'message'    => 'Ítem de agenda actualizado correctamente.',
            'agendaItem' => new AgendaItemResource($agenda_item),
        ]);
    }

    /**
     * Sin guarda de inscripciones: es un ítem de cronograma, ningún
     * participante lo referencia.
     */
    public function destroy(AgendaItem $agenda_item): JsonResponse
    {
        $this->assertCanWriteEvento((int) $agenda_item->event_id);

        $before = $agenda_item->toArray();
        $agenda_item->delete();

        AdminAuditLogger::log('delete', 'agenda_item', $agenda_item->id, (int) $agenda_item->event_id, $before, null);

        return response()->json([
            'success' => true,
            'message' => 'Ítem de agenda eliminado correctamente.',
        ]);
    }
}
