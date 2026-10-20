@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
	<div class="row align-items-center">
		<div class="col">
			<h1 class="h3">{{ translate('Guarantee') }}</h1>
		</div>
	</div>
</div>
<div class="row">
	<div class="col-md-8 mx-auto">
		<div class="card">
			<div class="card-header">
				<h6 class="mb-0">{{ translate('Guarantee') }}</h6>
			</div>
			<div class="card-body">
				<form action="{{ route('business_settings.update2') }}" method="POST" enctype="multipart/form-data">
					@csrf
				 
	                
				     <div class="form-group row">
						<label class="col-md-3 col-from-label">{{translate('Must Guarantee?')}}</label>
						<div class="col-md-8">
							<label class="aiz-switch aiz-switch-success mb-0">
								<input type="hidden" name="types[]" value="must_guarantee">
								<input type="checkbox" name="must_guarantee" @if( get_setting('must_guarantee') == 'on') checked @endif>
								<span></span>
							</label>
						</div>
					</div>
                    <div class="border-top pt-3">
                        <div class="form-group row">
							<label class="col-md-3 col-from-label">{{translate('Guarantee Money')}}</label>
							<div class="col-md-8">
								<div class="form-group">
									<input type="hidden" name="types[]" value="guarantee_money">
									<input type="text" class="form-control" placeholder="{{ translate('Guarantee Money') }}" name="guarantee_money" value="{{ get_setting('guarantee_money') }}">
								</div>
							</div>
						</div>
                    </div>
				     <div class="form-group row">
						<label class="col-md-3 col-from-label">{{translate('Guarantee Pay Close?')}}</label>
						<div class="col-md-8">
							<label class="aiz-switch aiz-switch-success mb-0">
								<input type="hidden" name="types[]" value="must_guarantee_close">
								<input type="checkbox" name="must_guarantee_close" @if( get_setting('must_guarantee_close') == 'on') checked @endif>
								<span></span>
							</label>
						</div>
					</div>
					 
					</div>
					<div class="text-right">
						<button style=" margin-right:20px;" type="submit" class="btn btn-primary">{{ translate('Update') }}</button>
					</div>
					<BR>
				</form>
			</div>
		</div>
	</div>
</div>

@endsection
