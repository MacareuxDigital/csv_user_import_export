<?php defined('C5_EXECUTE') or die("Access Denied."); ?>

<form method="post" action="<?= $view->action('export') ?>">
    <?php echo $token->output('export'); ?>
    <p>サイトに登録されているユーザーをCSVに出力します。</p>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?php echo URL::to('/dashboard/system') ?>"
               class="btn btn-default pull-left">キャンセル</a>
            <?php echo $form->submit('submit', 'エクスポート実行', array('class' => 'btn btn-primary pull-right'))?>
        </div>
    </div>
</form>
