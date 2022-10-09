<div class="tab-pane mt-3 fade " id="referral" role="tabpanel" aria-labelledby="referral-tab">
    <div class="row">
        <div class="col-12 col-md-6">
            <form action="/admin/settings/main" method="post">
                {{ csrf_field() }}
                <input type="hidden" name="page" value="financial">
                <input type="hidden" name="name" value="referral">


              
                <div class="empty-state mx-auto d-block"  data-width="900" >
                    <img class="img-fluid col-md-6" src="/assets/default/img/lic.svg" alt="image">
                    <h3 class="mt-3">Please activate your license!</h3>
                    <h5 class="lead">
                    You can activate your license by <strong><a href="mailto:rocketsoftsolutions@gmail.com">contacting support</a></strong> our checking <strong><a href="https://crm.rocket-soft.org/index.php/tickets">CRM</a></strong>  </h5>      
                  </div>


            </form>
        </div>
    </div>
</div>
