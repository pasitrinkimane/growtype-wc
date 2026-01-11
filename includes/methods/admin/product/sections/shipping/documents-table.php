<div class="options_group">
    <div class="form-field downloadable_files">
        <label><?php esc_html_e('Additional documents', 'growtype-wc'); ?></label>
        <table class="widefat">
            <thead>
            <tr>
                <th class="sort">&nbsp;</th>
                <th><?php esc_html_e('Name', 'growtype-wc'); ?><?php echo wc_help_tip(__('This is the name of the download shown to the customer.', 'growtype-wc')); ?></th>
                <th colspan="1"><?php esc_html_e('File URL', 'growtype-wc'); ?><?php echo wc_help_tip(__('This is the URL or absolute path to the file which customers will get access to. URLs entered here should already be encoded.', 'growtype-wc')); ?></th>
                <th><?php esc_html_e('Key', 'growtype-wc'); ?><?php echo wc_help_tip(__('This is the key of the product.', 'growtype-wc')); ?></th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $downloadable_files = isset($_GET['post']) ? Growtype_Wc_Product::shipping_documents($_GET['post']) : [];

            if ($downloadable_files) {
                foreach ($downloadable_files as $key => $file) {
                    include 'documents-table-row.php';
                }
            }
            ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="5">
                    <a href="#" class="button insert" data-row="
							<?php
                    $key = '';
                    $file = array (
                        'file' => '',
                        'name' => '',
                    );
                    ob_start();
                    require 'documents-table-row.php';
                    echo esc_attr(ob_get_clean());
                    ?>">
                        <?php esc_html_e('Add File', 'growtype-wc'); ?></a>
                </th>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
