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
                <button class="pull-right btn btn-primary" type="submit" id="import"><?=t('Import')?></button>
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
                <button class="pull-right btn btn-primary" type="submit" id="next"><?=t('Next')?></button>
            </div>
        </div>
    </form>

<?php } ?>

<?php if ($queueExists): ?>
    <script>
        $(document).ready(function () {

            let div = $('<div id="ccm-popup-confirmation" class="ccm-ui"><div id="ccm-popup-confirmation-message">' + <?php echo json_encode(t('A queue already exists! Would you like to continue it?')); ?> + '</div>');

            div.dialog({
                title: <?php echo json_encode(t('Confirm')); ?>,
                width: 500,
                maxHeight: 500,
                modal: true,
                dialogClass: 'ccm-ui',
                close: function() {
                    $div.remove();
                },
                buttons:[{}],
                'open': function () {
                    $(this).parent().find('.ui-dialog-buttonpane').addClass("ccm-ui").html('');
                    $(this).parent().find('.ui-dialog-buttonpane').append(
                        '<button data-dialog-action="submit-clear" ' +
                        'class="btn btn-danger">' + <?php echo json_encode(t('Clear')); ?> + '</button>' +
                        '<button data-dialog-action="submit-continue" ' +
                        'class="btn btn-default pull-right">' + <?php echo json_encode(t('Continue')); ?> + '</button></div>');
                }
            });

            div.parent().on('click', 'button[data-dialog-action=submit-clear]', function() {
                return window.location.href = "<?php echo $view->action('delete_queue', $token->generate('delete_queue')); ?>";
            });

            div.parent().on('click', 'button[data-dialog-action=submit-continue]', function() {
                $('#import').prop('disabled', true);
                $('#next').prop('disabled', true);

                ccm_triggerProgressiveOperation(
                    CCM_DISPATCHER_FILENAME + '/ccm/user_import_export/import',
                    [
                        {'name': 'data', 'value': JSON.stringify($('#importForm').serializeArray())}
                    ],
                    '<?= t('Importing Users. Please wait for a while...') ?>',
                    function () {
                        window.location.href = "<?=$this->action('imported') ?>";
                    }
                );
                div.dialog('close');
            });

        });
    </script>
<?php endif; ?>