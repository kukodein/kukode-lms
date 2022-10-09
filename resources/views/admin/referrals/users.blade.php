
@extends('admin.layouts.app')

@push('libraries_top')

@endpush


@section('content')
    <section class="section">
        <div class="section-header">
            <h1>{{trans('admin/main.affiliate_users')}}</h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="/admin/">{{trans('admin/main.dashboard')}}</a>
                </div>
                <div class="breadcrumb-item">{{trans('admin/main.affiliate_users')}}</div>
            </div>
        </div>

        <div class="section-body">

            <div class="row">
                <div class="col-12 col-md-12">
                    <div class="card">
                        <div class="card-body">



                            <div class="empty-state mx-auto d-block"  data-width="900" >
                                <img class="img-fluid col-md-6" src="/assets/default/img/lic.svg" alt="image">
                                <h3 class="mt-3">Please activate your license!</h3>
                                <h5 class="lead">
                                You can activate your license by <strong><a href="mailto:rocketsoftsolutions@gmail.com">contacting support</a></strong> our checking <strong><a href="https://crm.rocket-soft.org/index.php/tickets">CRM</a></strong>  </h5>  
                              </div>


                            
                        </div>

                      

                    </div>
                </div>
            </div>
        </div>
    </section>





@endsection

@push('scripts_bottom')

@endpush
