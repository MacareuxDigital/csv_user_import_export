<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<form method="post" action="<?= $view->action('export') ?>">
    <?php echo $token->output('export'); ?>
    <div class="form-group">
        <label class="control-label"><?=t('Export Group')?></label>
        <div class="controls">
            <?=$form->select('exportGroup', ['Comma Separated Single Column', 'Separate Columns', 'Do not export group'], '')?>
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
