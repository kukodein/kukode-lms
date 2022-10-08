<?php $__env->startPush('styles_top'); ?>
    <link rel="stylesheet" href="/assets/default/vendors/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.markercluster/markerCluster.css">
    <link rel="stylesheet" href="/assets/vendors/leaflet/leaflet.markercluster/markerCluster.Default.css">
    <link rel="stylesheet" href="/assets/vendors/wrunner-html-range-slider-with-2-handles/css/wrunner-default-theme.css">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
 <div class=" text-center d-block mt-50"  data-width="50%" >
                                <img class="col-md-3" src="/assets/default/img/plugin.svg" alt="image">
                                <h3 class="mt-3 mb-10">This is a paid plugin!</h3>
                                <h5 class="lead">
                                    You can purchase it by <strong><a class="text-danger" href="https://codecanyon.net/item/universal-plugins-bundle-for-rocket-lms/33297004">this link</a></strong> on Codecanyon.
                                </h5>             
                              </div>

<?php $__env->stopSection(); ?>


<?php $__env->startPush('scripts_bottom'); ?>
    <script src="/assets/vendors/wrunner-html-range-slider-with-2-handles/js/wrunner-jquery.js"></script>
    <script src="/assets/vendors/leaflet/leaflet.min.js"></script>
    <script src="/assets/vendors/leaflet/leaflet.markercluster/leaflet.markercluster-src.js"></script>
    <script src="/assets/default/vendors/swiper/swiper-bundle.min.js"></script>

    <script>
        var currency = '<?php echo e($currency); ?>';
        var profileLang = '<?php echo e(trans('public.profile')); ?>';
        var hourLang = '<?php echo e(trans('update.hour')); ?>';
        var mapUsers = JSON.parse(<?php echo json_encode($mapUsers->toJson(), 15, 512) ?>);
        var selectProvinceLang = '<?php echo e(trans('update.select_province')); ?>';
        var selectCityLang = '<?php echo e(trans('update.select_city')); ?>';
        var selectDistrictLang = '<?php echo e(trans('update.select_district')); ?>';
    </script>

    <script src="/assets/default/js/parts/instructor-finder-wizard.min.js"></script>
    <script src="/assets/default/js/parts/instructors.min.js"></script>

    <script src="/assets/default/js/parts/instructor-finder.min.js"></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('web.default.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/n1029452/public_html/cafecoding/resources/views/web/default/instructorFinder/index.blade.php ENDPATH**/ ?>