<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>

<?php if (isset($header) && is_array($header)) { ?>

    <form method="post" action="#" id="importForm">
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

    <script type="text/javascript">
        $(function () {
            var form = $('#importForm');
            form.on('submit', function () {
                $('#import').prop('disabled', true);
                ccm_triggerProgressiveOperation(
                    CCM_DISPATCHER_FILENAME + '/ccm/user_import_export/import',
                    [
                        {'name': 'ccm_token', 'value': <?=json_encode($token->generate('import'))?>},
                        {'name': 'fID', 'value': <?php echo $f->getFileID(); ?>},
                        {'name': 'data', 'value': JSON.stringify(form.serializeArray())}
                    ],
                    '<?= t('Importing Users. Please wait for a while...') ?>',
                    function () {
                        window.location.href = "<?=$this->action('imported') ?>";
                    }
                );
                return false;
            });
        });
    </script>

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

    <?php if ($queueExists): ?>
        <script>
            $(document).ready(function () {
                ConcreteAlert.confirm(
                    <?php echo json_encode(t('A queue already exists! Would you like to delete it?')); ?>,
                    function() {
                        window.location.href = "<?php echo $view->action('delete_queue', $token->generate('delete_queue')); ?>";
                    },
                    'btn-danger',
                    <?php echo json_encode(t('Delete')); ?>
                );
            });
        </script>
    <?php endif; ?>

<?php }
