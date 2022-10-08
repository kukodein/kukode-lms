<div class="tab-pane mt-3 fade" id="purchased_products" role="tabpanel" aria-labelledby="purchased_products-tab">
    <div class="row">

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin_enrollment_add_student_to_items')): ?>
            <div class="col-12 col-md-6">
                <h5 class="section-title after-line"><?php echo e(trans('update.add_student_to_product')); ?></h5>

                <form action="/admin/enrollments/store" method="Post">

                    <input type="hidden" name="user_id" value="<?php echo e($user->id); ?>">

                    <div class="form-group">
                        <label class="input-label"><?php echo e(trans('update.product')); ?></label>
                        <select name="product_id" class="form-control search-product-select2"
                                data-placeholder="<?php echo e(trans('update.search_product')); ?>">

                        </select>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class=" mt-4">
                        <button type="button" class="js-save-manual-add btn btn-primary"><?php echo e(trans('admin/main.submit')); ?></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="col-12">
            <div class="mt-5">
                <h5 class="section-title after-line"><?php echo e(trans('update.manual_added_products')); ?></h5>

                <div class="table-responsive mt-3">
                    <table class="table table-striped table-md">
                        <tr>
                            <th><?php echo e(trans('update.product')); ?></th>
                            <th><?php echo e(trans('admin/main.type')); ?></th>
                            <th><?php echo e(trans('admin/main.price')); ?></th>
                            <th><?php echo e(trans('update.seller')); ?></th>
                            <th class="text-center"><?php echo e(trans('update.added_date')); ?></th>
                            <th class="text-right"><?php echo e(trans('admin/main.actions')); ?></th>
                        </tr>

                        <?php if(!empty($manualAddedProducts)): ?>
                            <?php $__currentLoopData = $manualAddedProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $manualAddedProduct): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <tr>
                                    <td width="25%">
                                        <a href="<?php echo e($manualAddedProduct->productOrder->product->getUrl()); ?>" target="_blank" class=""><?php echo e($manualAddedProduct->productOrder->product->title); ?></a>
                                    </td>

                                    <td>
                                        <?php echo e(trans('update.product_type_'.$manualAddedProduct->productOrder->product->type)); ?>

                                    </td>
                                    <td><?php echo e(!empty($manualAddedProduct->productOrder->product->price) ? handlePrice($manualAddedProduct->productOrder->product->price) : '-'); ?></td>
                                    <td width="25%">
                                        <p><?php echo e($manualAddedProduct->productOrder->product->creator->full_name); ?></p>
                                    </td>
                                    <td class="text-center"><?php echo e(dateTimeFormat($manualAddedProduct->created_at,'j M Y | H:i')); ?></td>
                                    <td class="text-right">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin_enrollment_block_access')): ?>
                                            <?php echo $__env->make('admin.includes.delete_button',[
                                                    'url' => '/admin/enrollments/'. $manualAddedProduct->id .'/block-access',
                                                    'tooltip' => trans('update.block_access'),
                                                    'btnIcon' => 'fa-times-circle'
                                                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </table>
                    <p class="font-12 text-gray mt-1 mb-0"><?php echo e(trans('update.manual_add_hint')); ?></p>
                </div>
            </div>
        </div>


        <div class="col-12">
            <div class="mt-5">
                <h5 class="section-title after-line"><?php echo e(trans('update.manual_disabled_products')); ?></h5>

                <div class="table-responsive mt-3">
                    <table class="table table-striped table-md">
                        <tr>
                            <th><?php echo e(trans('update.product')); ?></th>
                            <th><?php echo e(trans('admin/main.type')); ?></th>
                            <th><?php echo e(trans('admin/main.price')); ?></th>
                            <th><?php echo e(trans('update.seller')); ?></th>
                            <th class="text-right"><?php echo e(trans('admin/main.actions')); ?></th>
                        </tr>

                        <?php if(!empty($manualDisabledProducts)): ?>
                            <?php $__currentLoopData = $manualDisabledProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $manualDisabledProduct): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <tr>
                                    <td width="25%">
                                        <a href="<?php echo e($manualDisabledProduct->productOrder->product->getUrl()); ?>" target="_blank" class=""><?php echo e($manualDisabledProduct->productOrder->product->title); ?></a>
                                    </td>

                                    <td>
                                        <?php echo e(trans('update.product_type_'.$manualDisabledProduct->productOrder->product->type)); ?>

                                    </td>
                                    <td><?php echo e(!empty($manualDisabledProduct->productOrder->product->price) ? handlePrice($manualDisabledProduct->productOrder->product->price) : '-'); ?></td>
                                    <td width="25%">
                                        <p><?php echo e($manualDisabledProduct->productOrder->product->creator->full_name); ?></p>
                                    </td>
                                    <td class="text-right">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin_enrollment_block_access')): ?>
                                            <?php echo $__env->make('admin.includes.delete_button',[
                                                    'url' => '/admin/enrollments/'. $manualDisabledProduct->id .'/enable-access',
                                                    'tooltip' => trans('update.enable-student-access'),
                                                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </table>
                    <p class="font-12 text-gray mt-1 mb-0"><?php echo e(trans('update.manual_remove_hint')); ?></p>
                </div>
            </div>
        </div>


        <div class="col-12">
            <div class="mt-5">
                <h5 class="section-title after-line"><?php echo e(trans('panel.purchased')); ?></h5>

                <div class="table-responsive mt-3">
                    <table class="table table-striped table-md">
                        <tr>
                            <th><?php echo e(trans('update.product')); ?></th>
                            <th><?php echo e(trans('admin/main.type')); ?></th>
                            <th><?php echo e(trans('admin/main.price')); ?></th>
                            <th><?php echo e(trans('update.seller')); ?></th>
                            <th class="text-center"><?php echo e(trans('panel.purchase_date')); ?></th>
                            <th><?php echo e(trans('admin/main.actions')); ?></th>
                        </tr>

                        <?php if(!empty($purchasedProducts)): ?>
                            <?php $__currentLoopData = $purchasedProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $purchasedProduct): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <tr>
                                    <td width="25%">
                                        <a href="<?php echo e($purchasedProduct->productOrder->product->getUrl()); ?>" target="_blank" class=""><?php echo e($purchasedProduct->productOrder->product->title); ?></a>
                                    </td>
                                    <td>
                                        <?php echo e(trans('update.product_type_'.$purchasedProduct->productOrder->product->type)); ?>

                                    </td>
                                    <td><?php echo e(!empty($purchasedProduct->productOrder->product->price) ? handlePrice($purchasedProduct->productOrder->product->price) : '-'); ?></td>
                                    <td width="25%">
                                        <p><?php echo e($purchasedProduct->productOrder->product->creator->full_name); ?></p>
                                    </td>
                                    <td class="text-center"><?php echo e(dateTimeFormat($purchasedProduct->created_at,'j M Y | H:i')); ?></td>
                                    <td class="text-right">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin_enrollment_block_access')): ?>
                                            <?php echo $__env->make('admin.includes.delete_button',[
                                                    'url' => '/admin/enrollments/'. $purchasedProduct->id .'/block-access',
                                                    'tooltip' => trans('update.block_access'),
                                                    'btnIcon' => 'fa-times-circle'
                                                ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </table>
                    <p class="font-12 text-gray mt-1 mb-0"><?php echo e(trans('update.purchased_hint')); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /home/n1029452/public_html/learn/resources/views/admin/users/editTabs/purchased_products.blade.php ENDPATH**/ ?>