<?php

use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\MulticolumnTextExportableAttributeInterface;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;

defined('C5_EXECUTE') or die('Access Denied.');

$importer = isset($importer) ? $importer : 'manual';
$importGroup = isset($importGroup) ? $importGroup : 0;
$app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
/** @var UserCategory $userCategory */
$userCategory = $app->make(UserCategory::class);
?>

<?php if (isset($header) && is_array($header)) { ?>

    <form method="post" id="importForm">
        <table class="table">
            <thead>
            <tr>
                <th><?=t('CSV Header'); ?></th>
                <th><?=t('Maps To'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($importer == 'auto') {
                foreach ($header as $item) {
                    $mapsTo = t('Ignore');
                    if ($item === 'uName') {
                        $mapsTo = t('User Name');
                    } elseif ($item === 'uEmail') {
                            $mapsTo = t('Email');
                    } elseif ($item === 'uDefaultLanguage') {
                            $mapsTo = t('Default Language');
                    } elseif ($item ===  'gName') {
                        $mapsTo = t('Group');
                    } elseif (substr($item, 0, 2) === 'g:') {
                        $mapsTo = str_replace('g:', '', $item);
                    } elseif (substr($item, 0, 2) === 'a:') {
                        $handle = str_replace('a:', '', $item);
                        if (strpos($handle, '[') !== false) {
                            $handle = explode('[', $handle)[0];
                        }
                        $key = $userCategory->getAttributeKeyByHandle($handle);
                        if ($key && ($key->getController() instanceof SimpleTextExportableAttributeInterface || $key->getController() instanceof MulticolumnTextExportableAttributeInterface)) {
                            $mapsTo = $key->getAttributeKeyDisplayName();
                        }
                    }
                    ?>
                    <tr>
                        <td><?= h($item) ?></td>
                        <td><?= h($mapsTo) ?></td>
                    </tr>
                <?php } ?>
            <?php } else {
                array_unshift($header, t('Ignore'));
                ?>
                <?php foreach ($columns as $field => $column) { ?>
                    <tr>
                        <td><?= $form->select($field, $header, false, ['default' => 'Please select']) ?></td>
                        <td><?= $column ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
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
            form.on('submit', function (e) {
                $('#import').prop('disabled', true);
                ccm_triggerProgressiveOperation(
                    CCM_DISPATCHER_FILENAME + '/ccm/user_import_export/import',
                    [
                        {'name': 'ccm_token', 'value': <?=json_encode($token->generate('import'))?>},
                        {'name': 'fID', 'value': <?php echo $f->getFileID(); ?>},
                        {'name': 'importer', 'value': <?= json_encode($importer) ?>},
                        {'name': 'importGroup', 'value': <?= json_encode($importGroup) ?>},
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
            <div class="form-group">
                <?php
                echo $form->label('csv', t('Select CSV File'));
                /** @var \Concrete\Core\Application\Service\FileManager $html */
                $html = Core::make('helper/concrete/file_manager');
                echo $html->file('csv', 'csv', t('Choose File'));
                ?>
            </div>
            <div class="form-group">
                <?php
                echo $form->label('importer', t('Import Option'));
                echo $form->select('importer', [
                    'manual' => t('Manual Mapping'),
                    'auto' => t('Automatic Mapping')
                ], $importer);
                ?>
            </div>
            <div id="exportGroupOption" class="form-group" style="display: none">
                <?php
                echo $form->label('importGroup', t('Import Group'));
                echo $form->select('importGroup', [
                    t('Comma Separated Single Column'),
                    t('Separate Columns'),
                    t('Do not import group')
                ], '');
                ?>
            </div>
        </fieldset>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <button class="pull-right btn btn-primary" type="submit" id="next"><?=t('Next')?></button>
            </div>
        </div>
    </form>

    <script>
        $(function () {
            $('#importer').on('change', function () {
                if ($(this).val() === 'auto') {
                    $('#exportGroupOption').show();
                } else {
                    $('#exportGroupOption').hide();
                }
            })
        })
    </script>
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