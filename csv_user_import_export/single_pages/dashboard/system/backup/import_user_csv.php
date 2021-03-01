<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php if (isset($header) && is_array($header)) { ?>

    <form method="post" action="<?=$view->action('import', $f->getFileID())?>">
        <?=$this->controller->token->output('import')?>
        <table class="table">
            <thead>
                <tr>
                    <th><?=t('Maps To'); ?></th>
                    <th><?=t('CSV Header'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $field => $column): ?>
                    <tr>
                        <td><?= $column ?></td>
                        <td><?= $form->select($field, $header, false, ['default' => 'Please select']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <button class="pull-right btn btn-primary" type="submit" ><?=t('Import')?></button>
            </div>
        </div>
    </form>

<?php } else { ?>

    <form method="post" action="<?=$view->action('select_mapping')?>">
        <?=$this->controller->token->output('select_mapping')?>
        <fieldset>
            <legend><?=t('Select CSV File')?></legend>
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
                <button class="pull-right btn btn-primary" type="submit" ><?=t('Next')?></button>
            </div>
        </div>
    </form>

<?php }
