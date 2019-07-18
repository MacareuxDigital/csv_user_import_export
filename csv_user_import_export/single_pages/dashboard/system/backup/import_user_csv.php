<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php if (isset($csvHeader) && is_array($csvHeader)) { ?>

    <form method="post" action="<?=$view->action('csv_import', $fID); ?>">
        <?=$this->controller->token->output('csv_import'); ?>
        <table class="table">
            <thead>
            <tr>
                <th><?=t('CSV Header');?></th>
                <th class="text-center"><?=tc('Direction', 'To');?></th>
                <th><?=t('Maps To');?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($headers as $handle => $name): ?>
                <tr>
                    <td><?= $form->select($handle, $csvHeader, $$handle) ?></td>
                    <td class="text-center"><i class="fa fa-arrow-right" aria-hidden="true"></i></td>
                    <td><?= h($name) ?> (<?= h($handle) ?>)</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <button class="pull-right btn btn-primary" type="submit" ><?=t('Import'); ?></button>
            </div>
        </div>
    </form>

<?php } else { ?>

    <form method="post" action="<?=$view->action('select_mapping'); ?>">
        <?=$this->controller->token->output('select_mapping'); ?>
        <fieldset>
            <legend><?=t('Select CSV File'); ?></legend>
            <div class="form-group">
                <?php
                /** @var \Concrete\Core\Application\Service\FileManager $html */
                $html = Core::make('helper/concrete/file_manager');
                echo $html->file('csv', 'csv', t('Choose File'));
                ?>
            </div>
        </fieldset>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <button class="pull-right btn btn-primary" type="submit" ><?=t('Next'); ?></button>
            </div>
        </div>
    </form>

<?php }
