<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<style>
    table.table td .add {
        display: none;
    }
</style>
<script>
    $(document).ready(function(){
        var actions = $("table td:last-child").html();
        // Append table with add row form on add new button click
        $(".add-new").click(function(){
            var index = $("table tbody tr:last-child").index();
            var row = '<tr>' +
                '<td><input type="text" class="form-control"></td>' +
                '<td><input type="text" class="form-control"></td>' +
                '<td>' + actions + '</td>' +
                '</tr>';
            $("table").append(row);
            $("table tbody tr").eq(index + 1).find(".add, .edit").toggle();
            $('[data-toggle="tooltip"]').tooltip();
        });

        // Add row on add button click
        $(document).on("click", ".add", function(){
            var empty = false;
            var index = $(this).parents("tr").index();
            var input = $(this).parents("tr").find('input[type="text"]');
            input.each(function(){
                if(!$(this).val()){
                    empty = true;
                }
            });

            if(!empty){
                input.each(function(){
                    $(this).parent("td").text($(this).val());
                });
                $(this).parents("tr").find(".add, .edit").toggle();
                $(".add-new").removeAttr("disabled");

                AddConfigData();
            }else{
                ConcreteAlert.dialog(<?=json_encode(t('Error'))?>, <?=json_encode(t('The data is empty'))?>);
            }
        });

        // Edit row on edit button click
        $(document).on("click", ".edit", function(){
            var index = $(this).parents("tr").index();
            $(this).parents("tr").find("td:not(:last-child)").each(function(key){
                var inner_text = $(this).text();
                $(this).html('<input id="config'+index+key+ '" type="text" class="form-control">');
                $("#config"+index+key).val(inner_text);
            });
            $(this).parents("tr").find(".add, .edit").toggle();
        });

        // Delete row on delete button click
        $(document).on("click", ".delete", function(){
            var name = $(this).parents("tr").find("td:eq(0)").text();
            var handle = $(this).parents("tr").find("td:eq(1)").text();

            if(name !== null && handle !== null){
                $.ajax({
                    url: "<?= $view->action('deleteConfig', $token->generate('perform_delete')) ?>",
                    data: {"name":name,"handle":handle},
                    type: 'post',
                    success: function(response) {
                        if(response){
                            ConcreteAlert.notify({
                                title: <?php echo json_encode(t('Successfully deleted')); ?>
                            });
                        }else{
                            ConcreteAlert.error({
                                title: <?php echo json_encode(t($token->getErrorMessage())); ?>
                            });
                        }
                    }
                });
            }
            $(this).parents("tr").remove();
        });

        function AddConfigData(){
            var config_data = {};

            $('table > tbody  > tr').each(function(index, tr) {
                var name = $(this).find("td:eq(0)").text();
                var handle = $(this).find("td:eq(1)").text();
                handle = handle.replace('[', '&lbrack;');
                handle = handle.replace(']', '&rbrack;');
                config_data[handle] = name;
            });

            if(Object.keys(config_data).length > 0){

                $.ajax({
                    url: "<?= $view->action('addConfig', $token->generate('perform_add')) ?>",
                    data: {"config_data":config_data},
                    type: 'post',
                    success: function(response) {
                        if(response){
                            ConcreteAlert.notify({
                                title: <?php echo json_encode(t('Successfully added')); ?>
                            });
                        }else{
                            ConcreteAlert.error({
                                title: <?php echo json_encode(t($token->getErrorMessage())); ?>
                            });
                        }
                    }
                });
            }
        }
    });
</script>
<div class="container-lg">
    <div class="table-wrapper">
        <div class="table-title">
            <div class="row">
                <div class="col-sm-10"><h2><?=t('CSV Header')?></h2></div>
                <div class="col-sm-2">
                    <button type="button" class="btn btn-info add-new"><i class="fa fa-plus"></i><?=t('Add New')?></button>
                </div>
            </div>
        </div>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th width="45%"><?=t('Name')?></th>
                <th width="40%"><?=t('Handle')?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($columns as $field => $column): ?>
                <tr>
                    <td><?= h($column) ?></td>
                    <td><?= h($field) ?></td>
                    <td>
                        <span class="btn btn-default btn-sm btn-primary add"><?=t('Add')?></span>
                        <span class="btn btn-default btn-sm btn-primary edit"><?=t('Edit')?></span>
                        <span class="btn btn-default btn-sm btn-danger delete"><?=t('Delete')?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>