<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<form method="post" action="<?= $view->action('export') ?>">
    <?php echo $token->output('export'); ?>
    <div class="form-group">
        <label class="control-label"><?=t('Export Group')?></label>
        <div class="controls">
            <?php
            echo $form->select('exportGroup', [
                t('Comma Separated Single Column'),
                t('Separate Columns'),
                t('Do not export group')
            ], '');
            ?>
        </div>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?php echo URL::to('/dashboard/system') ?>"
               class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <?php echo $form->submit('submit', t('Export'), ['class' => 'btn btn-primary pull-right'])?>
        </div>
    </div>
</form>
