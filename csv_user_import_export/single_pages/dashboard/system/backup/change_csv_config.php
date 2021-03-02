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
                    $(this).addClass("error");
                    empty = true;
                    alert("データを入力してください。");
                } else{
                    $(this).removeClass("error");
                }
            });
            $(this).parents("tr").find(".error").first().focus();
            if(!empty){
                input.each(function(){
                    $(this).parent("td").html($(this).val());
                });
                $(this).parents("tr").find(".add, .edit").toggle();
                $(".add-new").removeAttr("disabled");

                AddConfigData();
            }
        });

        // Edit row on edit button click
        $(document).on("click", ".edit", function(){
            $(this).parents("tr").find("td:not(:last-child)").each(function(key){
                $(this).html('<input type="text" class="form-control" value="' + $(this).text() + '">');
            });
            $(this).parents("tr").find(".add, .edit").toggle();
        });

        // Delete row on delete button click
        $(document).on("click", ".delete", function(){
            var name = $(this).parents("tr").find("td:eq(0)").text();
            var handle = $(this).parents("tr").find("td:eq(1)").text();

            if(name !== null && handle !== null){
                $.ajax({
                    url: "<?= $view->action('DeleteConfig') ?>",
                    data: {"name":name,"handle":handle},
                    type: 'post',
                    success: function(response) {

                    }
                });
            }
            $(this).parents("tr").remove();
            $(".add-new").removeAttr("disabled");
        });

        function AddConfigData(){
            var config_data = {};

            $('table > tbody  > tr').each(function(index, tr) {
                var name = $(this).find("td:eq(0)").text();
                var handle = $(this).find("td:eq(1)").text();
                config_data[handle] = name;
            });

            if(Object.keys(config_data).length > 0){

                $.ajax({
                    url: "<?= $view->action('AddConfig') ?>",
                    data: {"config_data":config_data},
                    type: 'post',
                    success: function(response) {
                        alert("success"+response);
                    }
                });
            }
        }
    });
</script>
</head>
<body>
<div class="container-lg">
    <div class="table-wrapper">
        <div class="table-title">
            <div class="row">
                <div class="col-sm-10"><h2>Change CSV Config</h2></div>
                <div class="col-sm-2">
                    <button type="button" class="btn btn-info add-new"><i class="fa fa-plus"></i> Add New</button>
                </div>
            </div>
        </div>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th width="45%">Name</th>
                <th width="40%">Handle</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($columns as $field => $column): ?>
                <tr>
                    <td><?= h($column) ?></td>
                    <td><?= h($field) ?></td>
                    <td>
                        <a href="#" class="btn btn-default btn-sm btn-primary add"><?=t('Add')?></a>
                        <a href="#" class="btn btn-default btn-sm btn-primary edit"><?=t('Edit')?></a>
                        <a href="#" class="btn btn-default btn-sm btn-danger delete"><?=t('Delete')?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>