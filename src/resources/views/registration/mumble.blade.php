@extends('web::layouts.grids.12')

@section('title', trans('seat-mumble-connector::seat.mumble_registration'))
@section('page_header', trans('seat-mumble-connector::seat.mumble_registration'))

@section('full')

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ trans('seat-mumble-connector::seat.mumble_registration') }}</h3>
            </div>
            <div class="card-body">
                @if(!is_null($driver_user->connector_id))
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> {{ trans('seat-mumble-connector::seat.existing_registration') }}
                    </div>
                @else
                    <div class="alert alert-success">
                        <i class="fas fa-plus"></i> {{ trans('seat-mumble-connector::seat.new_registration') }}
                    </div>
                @endif

                <form method="post" id="MumbleForm" action="{{ route('seat-connector.drivers.mumble.registration.submit') }}">
                    {{ csrf_field() }}
                    
                    <div class="form-group">
                        <label for="mumble_username">{{ trans('seat-mumble-connector::seat.username') }}</label>
                        <input class="form-control" type="text" 
                               name="mumble_username"
                               id="mumble_username"
                               value="{{ $driver_user->connector_name }}"
                               @if(!is_null($driver_user->connector_id)) disabled @endif
                               placeholder="{{ trans('seat-mumble-connector::seat.username_placeholder') }}"
                               required>
                        <small class="form-text text-muted">{{ trans('seat-mumble-connector::seat.username_help') }}</small>
                    </div>

                    @if(is_null($driver_user->connector_id))
                    <div class="form-group">
                        <label for="mumble_password">{{ trans('seat-mumble-connector::seat.password') }}</label>
                        <input class="form-control" type="password" 
                               name="mumble_password"
                               id="mumble_password"
                               placeholder="{{ trans('seat-mumble-connector::seat.password_placeholder') }}"
                               required>
                        <small class="form-text text-muted">{{ trans('seat-mumble-connector::seat.password_help') }}</small>
                    </div>
                    @endif

                    <div class="form-group">
                        <label for="nickname">{{ trans('seat-mumble-connector::seat.nickname') }}</label>
                        <input class="form-control" type="text" 
                               name="nickname"
                               id="nickname"
                               value="{{ $driver_user->nickname }}"
                               placeholder="{{ trans('seat-mumble-connector::seat.nickname_placeholder') }}">
                        <small class="form-text text-muted">{{ trans('seat-mumble-connector::seat.nickname_help') }}</small>
                    </div>

                    <div class="form-group">
                        <label for="seat_user">{{ trans('web::seat.seat_user') }}</label>
                        <input class="form-control" type="text" 
                               name="seat_user"
                               id="seat_user"
                               value="{{ $seat_user->name }}"
                               disabled>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                @if($allow_registration || !is_null($driver_user->connector_id))
                    <button type="submit" form="MumbleForm" class="btn btn-success float-right">
                        @if(is_null($driver_user->connector_id))
                            {{ trans('seat-mumble-connector::seat.register') }}
                        @else
                            {{ trans('seat-mumble-connector::seat.update') }}
                        @endif
                    </button>
                @else
                    <div class="alert alert-warning">
                        {{ trans('seat-mumble-connector::seat.registration_disabled') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ trans('seat-mumble-connector::seat.mumble_info') }}</h3>
            </div>
            <div class="card-body">
                <h5>{{ trans('seat-mumble-connector::seat.what_is_mumble') }}</h5>
                <p>{{ trans('seat-mumble-connector::seat.mumble_description') }}</p>
                
                <h5>{{ trans('seat-mumble-connector::seat.connection_info') }}</h5>
                <ul>
                    <li><strong>{{ trans('seat-mumble-connector::seat.server') }}:</strong> {{ setting('seat-connector.drivers.mumble.mumble_server_host', 'Not configured') }}</li>
                    <li><strong>{{ trans('seat-mumble-connector::seat.port') }}:</strong> {{ setting('seat-connector.drivers.mumble.mumble_server_port', 'Not configured') }}</li>
                </ul>
                
                <h5>{{ trans('seat-mumble-connector::seat.features') }}</h5>
                <ul>
                    <li>{{ trans('seat-mumble-connector::seat.feature_voice_chat') }}</li>
                    <li>{{ trans('seat-mumble-connector::seat.feature_channels') }}</li>
                    <li>{{ trans('seat-mumble-connector::seat.feature_permissions') }}</li>
                    <li>{{ trans('seat-mumble-connector::seat.feature_integration') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@stop