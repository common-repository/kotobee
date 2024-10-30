<?php
if(!isset($kotobeeItems))
    return;

if(!$kotobeeItems['Success']) {
    echo esc_html($kotobeeItems['Message']);
    return;
}
if(!count($kotobeeItems['Object']['libraries']) && !count($kotobeeItems['Object']['books'])) {
    echo "<p>".__("You do not have any books or libraries in your Kotobee account", KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
    return;
}

$libraries = $kotobeeItems['Object']['libraries'];
$books = $kotobeeItems['Object']['books'];
?>

<style>
    .kotobee-tab-content td, .kotobee-tab-content .th {
        width: 50%;
    }
    .more-options-button{
        position: relative;
        display: none;
    }
    .more-options-button:after {
        right: 0;
        content: "\f140";
        font: 400 20px/1 dashicons;
        speak: none;
        display: inline-block;
        bottom: 2px;
        position: relative;
        vertical-align: bottom;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-decoration: none!important;
        color: #72777c;
    }
    .more-options-button[data-status=open]:after {
        content: "\f142";
    }
    .book-more-options {
        display: none;
        margin-top: 5px;
    }
    .book-more-options label {
        font-weight: bold;
        padding: 2px;
        display: block;
    }
    input.book-chapters {
        line-height:20px;
    }
</style>
<div id="kotobee-book-selection" style="display:none; padding:10px 20px;">
    <h2 class="nav-tab-wrapper">
        <a id="libraryTab" href="javascript:void(0)" class="nav-tab kotobee-tab nav-tab-active"><?php esc_html_e('Library', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></a>
        <a id="cloudTab" href="javascript:void(0)" class="nav-tab kotobee-tab "><?php esc_html_e('Cloud Ebooks', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></a>
    </h2>
    <div class="kotobee-tab-content" data-tab="libraryTab" style="display:none;">
        <?php if(count($libraries)): ?>
            <div style="text-align:center; margin: 10px 0;">
                <label for="librarySelect"><?php esc_html_e('Select library:', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?> </label>
                <select id="librarySelect">
                    <?php
                    foreach($libraries as $library):
                        ?>
                        <option value="<?php echo esc_attr($library['lib']['id']); ?>"><?php echo esc_html($library['lib']['name']); ?></option>
                    <?php
                    endforeach;
                    ?>
                </select>
            </div>
            <?php
            foreach($libraries as $library):
                ?>
                <div class="libraryContainer" data-id="<?php echo esc_attr($library['lib']['id']); ?>">
                    <table class="widefat" style="margin: 10px 0;">
                        <?php
                        $alternate = '1';
                        $alternate = $alternate==''?'alternate':'';
                        ?>
                        <tr class="<?php echo esc_attr($alternate); ?>" data-type='<?php echo esc_attr($this->kotobeeTypes['library']); ?>' data-id='<?php echo esc_attr($library['lib']['id']);?>'>
                            <td class="row-title"><?php esc_html_e('Global Access', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></td>
                            <td>
                                <a href="javascript:void(0)" class="button link-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                <div class="spinner"></div>
                            </td>
                        </tr>
                    </table>
                    <h2><?php esc_html_e('Roles', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></h2>
                    <?php
                    $libraryRoles = $library['lib']['cloud']['roles'];
                    if(empty($libraryRoles)):
                        echo "<p>".__('You do not have any roles defined in this library.',KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
                    else:
                        ?>
                        <!-- Library categories -->
                        <table class="widefat">
                            <thead>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Role Name', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $alternate = '1';
                            foreach($libraryRoles as $role):
                                $alternate = $alternate==''?'alternate':'';
                                ?>
                                <tr class="<?php echo esc_attr($alternate);?>" data-type='<?php echo esc_attr($this->kotobeeTypes['role']);?>' data-id='<?php echo esc_attr($role['id']);?>'>
                                    <td class="row-title"><?php echo esc_html($role['title']); ?></td>
                                    <td>
                                        <a href="javascript:void(0)" class="button link-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                        <div class="spinner"></div>
                                    </td>
                                </tr>
                            <?php
                            endforeach;
                            ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Role Name', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    <?php
                    endif; //End if categories exist
                    ?>
                    <h2><?php esc_html_e('Categories', KOTOBEE_INTEGRATION_TEXTDOMAIN); ?></h2>
                    <?php
                    $libraryCategories = $library['lib']['categories'];
                    if(empty($libraryCategories)):
                        echo "<p>".__('You do not have any categories in this library.',KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
                    else:
                        ?>
                        <!-- Library categories -->
                        <table class="widefat">
                            <thead>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Category Name', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $alternate = '1';
                            foreach($libraryCategories as $cat):
                                $alternate = $alternate==''?'alternate':'';
                                ?>
                                <tr class="<?php echo esc_attr($alternate);?>" data-type='<?php echo esc_attr($this->kotobeeTypes['cat']);?>' data-id='<?php echo esc_attr($cat['id']);?>'>
                                    <td class="row-title"><?php echo esc_html($cat['name']);?></td>
                                    <td>
                                        <a href="javascript:void(0)" class="button link-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                        <div class="spinner"></div>
                                    </td>
                                </tr>
                            <?php
                            endforeach;
                            ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Category Name', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    <?php
                    endif; //End if categories exist
                    $libraryBooks = $library['lib']['books'];
                    ?>
                    <h2><?php esc_html_e( 'Books', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></h2>
                    <?php
                    if(empty($libraryBooks)):
                        echo "<p>".__('You do not have any books in this library.',KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
                    else:
                        ?>
                        <table class="widefat">
                            <thead>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Book Title', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $alternate = '';
                            foreach($libraryBooks as $book):
                                $alternate = $alternate==''?'alternate':'';
                                ?>
                                <tr class="<?php echo esc_attr($alternate);?>" data-type='<?php echo esc_attr($this->kotobeeTypes['book']); ?>' data-id='<?php echo esc_attr($book['id']);?>'>
                                    <td class="row-title"><?php echo esc_html($book['name']); ?></td>
                                    <td>
                                        <a href="javascript:void(0)" class="button link-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                        <a href="javascript:void(0)" class="button more-options-button button-default" data-status="closed" onclick="toggleMoreOptions(this)"><?php esc_html_e('Options', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                        <div class="spinner"></div>
                                        <div class="book-more-options">
                                            <label><?php esc_html_e("Specific chapter access:", KOTOBEE_INTEGRATION_TEXTDOMAIN);?></label>
                                            <input class="book-chapters" type="text" placeholder="0,2,5" tooltip="<?php esc_attr_e("Enter chapter indices separated by commas, or leave empty for global book access", KOTOBEE_INTEGRATION_TEXTDOMAIN);?>" />
                                            <a href="javascript:void(0)" class="button save-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Save', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            endforeach;

                            ?>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th class="row-title"><?php esc_html_e( 'Book Title', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                                <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                            </tr>
                            </tfoot>
                        </table>
                    <?php
                    endif; // End of foreach library book
                    ?>
                </div>
            <?php
            endforeach; // End of foreach library
        else:
            echo "<p>".__('You do not have any libraries in your Kotobee account.',KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
        endif;
        ?>
    </div>
    <div class="kotobee-tab-content" data-tab="cloudTab" style="display:none; margin-top:10px;">
        <?php if(count($books)): ?>
            <table class="widefat">
                <thead>
                <tr>
                    <th class="row-title"><?php esc_html_e( 'Book Title', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $alternate = '1';
                if(count($books)) {
                    foreach($books as $book):
                        $alternate = $alternate==''?'alternate':'';
                        ?>
                        <tr class="<?php echo esc_attr($alternate); ?>" data-type='<?php echo esc_attr($this->kotobeeTypes['cloud']); ?>' data-id='<?php echo esc_attr($book['id']); ?>'>
                            <td class="row-title"><?php echo esc_html($book['name']); ?></td>
                            <td>
                                <a href="javascript:void(0)" class="button link-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Link', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                <a href="javascript:void(0)" class="button more-options-button button-default" data-status="closed" onclick="toggleMoreOptions(this)"><?php esc_html_e('Options', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                <div class="spinner"></div>
                                <div class="book-more-options">
                                    <label><?php esc_html_e("Specific chapter access:", KOTOBEE_INTEGRATION_TEXTDOMAIN);?></label>
                                    <input class="book-chapters" type="text" placeholder="0,2,5" tooltip="<?php esc_attr_e("Enter chapter indices separated by commas, or leave empty for global book access", KOTOBEE_INTEGRATION_TEXTDOMAIN);?>" />
                                    <a href="javascript:void(0)" class="button save-button button-primary" onclick="linkItem(this)"><?php esc_html_e('Save', KOTOBEE_INTEGRATION_TEXTDOMAIN);?></a>
                                </div>
                            </td>
                        </tr>
                    <?php
                    endforeach;
                }

                ?>
                </tbody>
                <tfoot>
                <tr>
                    <th class="row-title"><?php esc_html_e( 'Book Title', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                    <th><?php esc_html_e( 'Action', KOTOBEE_INTEGRATION_TEXTDOMAIN ); ?></th>
                </tr>
                </tfoot>
            </table>
        <?php
        else:
            echo "<p>".__('You do not have any cloud ebooks in your Kotobee account.',KOTOBEE_INTEGRATION_TEXTDOMAIN)."</p>";
        endif; ?>
    </div>

</div>
<script>
    jQuery(function() {
        jQuery(".kotobee-tab").click(function(event) {
            var item = event.currentTarget;
            showTabContent(item.id);
        });
        jQuery('#librarySelect').change(showLibraryContainer);

        let selectedTabID = jQuery(".kotobee-tab").first().attr('id');
        showTabContent(selectedTabID);
        showLibraryContainer();
    });
</script>