@extends('layouts.app', ['fullscreen' => true])

@section('content')

    <a href="{{ route('files.index') }}" class="btn btn-outline-primary"
       style="position: fixed; top: 10px; left: 10px; z-index: 20">
        Voltar
    </a>

    <div class="card" style="position: fixed; top: 60px; left: 10px; z-index: 20; max-width: 180px">
        <div class="card-header">
            Opções
        </div>
        <div class="card-body">
            <form action="{{ route('files.map', $file->id) }}" method="get">
                <label for="max_d">
                    <small>Cluster Max:</small>
                    <input type="number" name="max_d" id="max_d" step="0.001" value="{{ old('max_d', $max_d) }}"
                           class="form-control form-control-sm">
                </label>

                <button type="submit" class="btn btn-block btn-outline-success btn-sm"><i class="fa fa-refresh"></i>
                    Atualizar
                </button>
            </form>
        </div>
    </div>

    <div class="card" style="position: fixed; top: 10px; right: 10px; z-index: 20; max-width: 3000px">
        <div class="card-header">
            Detalhes
        </div>
        <ul class="list-group list-group-compact list-group-flush">
            <li class="list-group-item">Qtd. total: <b>{{ $results->count() }}</b></li>
            <li class="list-group-item">Qtd. clusters: <b>{{ $clusters->count() }}</b></li>
            <li class="list-group-item">Qtd. maior cluster: <b>{{ optional($clusters->first())['count'] }}</b></li>
        </ul>
    </div>

    @if($processing)
        @component('components.modal', ['id' => 'processing-modal'])

            @slot('title')
                <i class="fa fa-refresh fa-spin mr-2"></i>Processando mapa
            @endslot

            <p>
                Ops! ainda estamos processando o mapa... <br/>Aguarde alguns instantes antes de atualizar.
            </p>

            @slot('footer')
                <a href="{{ request()->url() }}" class="btn btn-success disabled" id="refresh-btn">
                    <i class="fa fa-refresh mr-2"></i>
                    Atualizar <span class="counter">( 20s )</span>
                </a>
            @endslot

        @endcomponent
    @endif

    <div id="geolv-container" style="position: fixed; width: 100%; height: 100%">
        <div class="geolv-map" style="width: 100%; height: 100%">
        </div>

        @foreach ($results as $result)
            <div class="geolv-result"
                 data-street-name="{{ $result->text }}"
                 data-latitude="{{ $result->latitude }}"
                 data-longitude="{{ $result->longitude }}"
                 data-cluster="{{ optional($result)->cluster }}"
                 data-provider="geolv"></div>
        @endforeach
    </div>

@endsection

@section('scripts')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initMap" async
            defer></script>
    @if($processing)
        <script>
            $(function () {
                $('#processing-modal').modal();
                var refreshBtn = $('#refresh-btn');
                var counter = refreshBtn.find('.counter');
                var secondsBeforeExpire = 20;
                var timer = setInterval(function () {
                    if (secondsBeforeExpire <= 0) {
                        clearInterval(timer);
                        refreshBtn.prop('disabled', false);
                        refreshBtn.removeClass('disabled');
                        counter.text("");
                    } else {
                        refreshBtn.prop('disabled', true);
                        refreshBtn.addClass('disabled');
                        secondsBeforeExpire--;
                        counter.text("( " + secondsBeforeExpire + "s )");
                    }
                }, 1000);
            });
        </script>
    @endif
@endsection