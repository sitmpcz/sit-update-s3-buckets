<?php

// Stary bucket
$old_bucket = get_option( "sits3_old_bucket" );

$settings = sits3_get_s3_settings();
$bucket = $settings["bucket"];
?>
<div class="wrap">
    <h1>Update S3 buckets</h1>
    <p>Update S3 paths after moving to new S3 buckets</p>
    <form method="post" action="options.php">
        <?php
        settings_fields("sits3_options");
        do_settings_sections("sits3_options");
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Nastavení</th>
                <td>
                    <?php
                    if ( $settings ) {
                        echo '<p><b>Starý bucket:</b> '. $settings["sits3_old_bucket"] .'</p>';
                        echo '<p><b>Nový bucket:</b> '. $bucket .'</p>';
                        //echo '<p><b>Cesta asi bude:</b> '. $settings["s3_url"] .'</p>';
                    }
                    ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Starý bucket</th>
                <td>
                    <input type="text" name="sits3_old_bucket" value="<?php echo $old_bucket; ?>" id="sits3_old_bucket" class="regular-text code" />
                    <p>Hledám toto</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Nový bucket</th>
                <td>
                    <input type="text" name="sits3_new_bucket" value="<?php echo $bucket; ?>" id="sits3_new_bucket" class="regular-text code" disabled />
                    <p>a nahrazuju tímto</p>
                </td>
            </tr>
            <?php
            if ( sits3_check_is_s3() === true ) :
                ?>
                <tr valign="top">
                    <th scope="row"></th>
                    <td>
                        <a href="<?php echo sits3_get_run_url(); ?>" class="button button-secondary">Spustit update</a>
                        <p>Jestli si jseš jistej, tak to zmáčkni :D</p>
                    </td>
                </tr>
            <?php
            endif;
            ?>
        </table>
        <?php
        submit_button();
        ?>
    </form>
</div>
