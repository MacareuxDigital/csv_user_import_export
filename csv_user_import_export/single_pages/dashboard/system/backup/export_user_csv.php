<?php defined('C5_EXECUTE') or die("Access Denied."); ?>

<form method="post" action="<?= $view->action('export') ?>">
    <?php echo $token->output('export'); ?>
    <p><?= t('Output users registered on the site to CSV.') ?></p>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?php echo URL::to('/dashboard/system') ?>"
               class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <?php echo $form->submit('submit', t('Export'), array('class' => 'btn btn-primary pull-right'))?>
        </div>
    </div>
</form>
