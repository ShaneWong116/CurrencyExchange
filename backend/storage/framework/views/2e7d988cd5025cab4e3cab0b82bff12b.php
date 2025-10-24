<div
    <?php echo e($attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)); ?>

>
    <?php echo e($getChildComponentContainer()); ?>

</div>
<?php /**PATH E:\PROJECT\CurrencyExSystem\CurrencyExSystem\ExchangeSystem\backend\vendor\filament\infolists\src\/../resources/views/components/grid.blade.php ENDPATH**/ ?>