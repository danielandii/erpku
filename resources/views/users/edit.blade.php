@extends('layout')

@section('title','Users Edit')

@section('content')

	<!-- Page header -->
	<div class="page-header page-header-light">
		<div class="page-header-content header-elements-md-inline">
			<div class="page-title d-flex">
				<h4>Ubah User</h4>
				<a href="#" class="header-elements-toggle text-default d-md-none"><i class="icon-more"></i></a>
			</div>
		</div>
	</div>
	<!-- /page header -->

	<!-- Content area -->
	<div class="content">

		<!-- Hover rows -->
		<div class="card">
			<div class="card-header header-elements-inline">
			</div>
			<div class="card-body">
				<form class="form-validate-jquery" action="{{ url('users/'.$user->id) }}" method="post">
					@method('PATCH')
					@csrf
					<fieldset class="mb-3">
						<legend class="text-uppercase font-size-sm font-weight-bold">Data User</legend>

                        <div class="form-group row">
							<label class="col-form-label col-lg-2">Username <span class="text-danger">*</span> </label>
							<div class="col-lg-10">
								<input type="text" name="username" class="form-control border-teal border-1 @error('username') is-invalid @enderror" placeholder="Username" required autocomplete="off" value="{{ ( old('username') ) ? old('username') : $user->username }}">
							</div>
						</div>
                        <div class="form-group row">
							<label class="col-form-label col-lg-2">Password</label>
							<div class="col-lg-10">
								<input type="password" name="password" class="form-control border-teal border-1 @error('password') is-invalid @enderror" placeholder="Password" autocomplete="off" value="{{ old('password') }}">
							</div>
						</div>
						<div class="form-group row">
							<label class="col-form-label col-lg-2">Nama <span class="text-danger">*</span> </label>
							<div class="col-lg-10">
								<input type="text" name="name" class="form-control border-teal border-1 @error('nama') is-invalid @enderror" placeholder="Nama" required autofocus autocomplete="off" value="{{ ( old('nama') ) ? old('nama') : $user->name }}">
							</div>
						</div>
						<div class="form-group row">
							<label class="col-form-label col-lg-2">Email <span class="text-danger">*</span> </label>
							<div class="col-lg-10">
								<input type="email" name="email" class="form-control border-teal border-1 @error('email') is-invalid @enderror" placeholder="Email" required value="{{ ( old('email') ) ? old('email') : $user->email }}" autocomplete="off">
							</div>
						</div>
						<div class="form-group row">
							<label class="col-form-label col-lg-2">Role <span class="text-danger">*</span> </label>
							<div class="col-lg-10">
								<select name="role_id" class="form-control form-control-select2" data-container-css-class="border-teal" data-dropdown-css-class="border-teal" required>
                                   @foreach ($roles as $role)
                                    <option value="{{$role->id}}" {{$role->id == $user->role_id ?'selected':''}} >{{$role->name}}</option>
                                   @endforeach
                                </select>
							</div>
                        </div>
					</fieldset>
					<div class="text-right">
						<a href="{{ url('/users')}}" class="btn btn-light">Kembali <i class="icon-undo"></i></a>
						<button type="submit" class="btn btn-primary">Update <i class="icon-paperplane ml-2"></i></button>
					</div>
				</form>
			</div>

		</div>
		<!-- /hover rows -->

	</div>
	<!-- /content area -->
@endsection

@section('js')
	<!-- Theme JS files -->
	<script src="{{asset('global_assets/js/plugins/notifications/pnotify.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/forms/validation/validate.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/forms/selects/select2.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/buttons/spin.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/buttons/ladda.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/daterangepicker.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/anytime.min.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/pickadate/picker.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/pickadate/picker.date.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/pickadate/picker.time.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/pickers/pickadate/legacy.js')}}"></script>
	<script src="{{asset('global_assets/js/plugins/forms/styling/uniform.min.js')}}"></script>

	<script src="{{asset('assets/js/app.js')}}"></script>

	<script type="text/javascript">

	</script>
	<script type="text/javascript">
		$( document ).ready(function() {

			var $select = $('.form-control-select2').select2();

	        // Default style
	        @if(session('error'))
	            new PNotify({
	                title: 'Error',
	                text: '{{ session('error') }}.',
	                icon: 'icon-blocked',
	                type: 'error'
	            });
            @endif
            @if ( session('success'))
	            new PNotify({
	                title: 'Success',
	                text: '{{ session('success') }}.',
	                icon: 'icon-checkmark3',
	                type: 'success'
	            });
            @endif
            @if ($errors->any())
				@foreach ($errors->all() as $error)
					new PNotify({
						title: 'Error',
						text: '{{ $error }}.',
						icon: 'icon-blocked',
						type: 'error'
					});
				@endforeach
			@endif

		});
	</script>

@endsection
