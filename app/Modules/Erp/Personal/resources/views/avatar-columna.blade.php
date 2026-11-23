{{-- MV-067: la foto o el avatar del colaborador en la tabla de fichas laborales. --}}
<div style="padding: 0.25rem 0 0.25rem 0.75rem">@include('personal::avatar', ['a' => \App\Modules\Erp\Personal\Services\PersonalService::avatar($getRecord()), 'tam' => 36])</div>
