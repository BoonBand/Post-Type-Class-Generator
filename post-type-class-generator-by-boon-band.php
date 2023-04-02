<?php
/*
Plugin Name: Post Type Class Generator by Boon.Band
Plugin URI: https://github.com/BoonBand/Post-Type-Class-Generator
Description: Generate a PHP class based on a WordPress post type with getters and setters for all its meta fields.
Version: 1.0
Author: Boon.Band
Author URI: https://www.boon.band/
License: GPL2
*/

// Main functionality
function generate_post_type_class($post_type)
{
    $temp = acf_get_field_groups(array('post_type' => $post_type));

    // Initialize an empty array for meta_keys
    $meta_keys = array();

    // Check if the array is not empty before processing ACF fields
    if (!empty($temp)) {
        $meta_keys = acf_get_fields($temp[0]['key']);
    }

    $class_name = ucwords(str_replace('_', ' ', $post_type));
    $class_name = str_replace(' ', '', $class_name);

    $output = "<?php\n\n";
    $output .= "class $class_name {\n\n";

    $property_names = generate_properties($output, $meta_keys);
    $property_field_map = []; // Add this line to initialize the variable
    generate_constructor($output, $meta_keys, $property_names, $property_field_map);
    generate_getters($output, $property_names);
    generate_setters($output, $property_names);

    generate_get_objects_method($output, $post_type);
    generate_save_method($output, $property_names, $property_field_map);
    generate_get_posts_count_method($output, $post_type);
    generate_filter_method($output, $post_type);
    generate_get_posts_count_by_meta_method($output, $post_type);
    generate_delete_method($output);


    $output .= "}\n\n";

    return $output;
}

function generate_properties(&$output, $meta_keys)
{
    $property_names = [
        'id',
        'title',
        'content',
        'excerpt',
        'img',
        'dateCreate',
        'dateUpdate',
        'author'
    ];

    // Generate properties
    $output .= "\tprivate \$id;\n";
    $output .= "\tprivate \$title;\n";
    $output .= "\tprivate \$content;\n";
    $output .= "\tprivate \$excerpt;\n";
    $output .= "\tprivate \$img;\n";
    $output .= "\tprivate \$dateCreate;\n";
    $output .= "\tprivate \$dateUpdate;\n";
    $output .= "\tprivate \$author;\n";

    foreach ($meta_keys as $field) {
        if ($field['label'] && $field['type'] != 'tab') {
            $property_name = ucwords(str_replace('_', ' ', $field['label']));
            $property_name = lcfirst(str_replace(' ', '', $property_name));
            $property_name = str_replace('.', '', $property_name);

            // Ensure unique property names
            while (in_array($property_name, $property_names)) {
                $property_name .= '_';
            }
            $property_names[] = $property_name;

            $output .= "\tprivate $$property_name;\n";
        }
    }

    return $property_names;
}


function generate_constructor(&$output, $meta_keys, $property_names, &$property_field_map)
{
    // Generate constructor
    $output .= "\n\tpublic function __construct(\$post_id) {\n";
    $output .= "\t\t\$post = get_post(\$post_id);\n\n";
    $output .= "\t\t\$this->id = \$post_id;\n";
    $output .= "\t\t\$this->title = \$post->post_title;\n";
    $output .= "\t\t\$this->content = \$post->post_content;\n";
    $output .= "\t\t\$this->excerpt = \$post->post_excerpt;\n";
    $output .= "\t\t\$this->img = get_the_post_thumbnail_url(\$post_id);\n";
    $output .= "\t\t\$this->dateCreate = \$post->post_date;\n";
    $output .= "\t\t\$this->dateUpdate = \$post->post_modified;\n";
    $output .= "\t\t\$this->author = \$post->post_author;\n";

    // Add ACF field values
    foreach ($meta_keys as $field) {
        if ($field['label'] && $field['type'] != 'tab') {
            $property_name = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field['label'])))); // Convert field name to property name
            $property_name = str_replace('.', '', $property_name);
            if (in_array($property_name, $property_names)) {
                $property_field_map[$property_name] = $field['name'];
                $output .= "\t\t\$this->$property_name = get_field('{$field['name']}', \$post_id);\n";
            }
        }
    }

    $output .= "\t}\n\n";
}


function generate_getters(&$output, $property_names)
{
    // Generate getters
    foreach ($property_names as $property_name) {
        $getter_name = 'get' . ucfirst($property_name);
        $output .= "\tpublic function $getter_name() {\n";
        $output .= "\t\treturn \$this->$property_name;\n";
        $output .= "\t}\n\n";
    }
}

function generate_setters(&$output, $property_names)
{
    // Generate setters
    foreach ($property_names as $property_name) {
        if ($property_name == 'id') continue; // Skip the ID property (it's read-only
        $setter_name = 'set' . ucfirst($property_name);
        $output .= "\tpublic function $setter_name(\$value) {\n";
        $output .= "\t\t\$this->$property_name = \$value;\n";
        $output .= "\t}\n\n";
    }
}

function generate_get_objects_method(&$output, $post_type)
{
    $output .= "\tpublic static function getLatest(\$posts_per_page = 10, \$page_number = 1, \$orderby = 'date', \$order = 'DESC', \$filters = []) {\n";
    $output .= "\t\t\$args = [\n";
    $output .= "\t\t\t'post_type'      => '$post_type',\n";
    $output .= "\t\t\t'posts_per_page' => \$posts_per_page,\n";
    $output .= "\t\t\t'paged'          => \$page_number,\n";
    $output .= "\t\t\t'orderby'        => \$orderby,\n";
    $output .= "\t\t\t'order'          => \$order,\n";
    $output .= "\t\t\t'meta_query'     => []\n";
    $output .= "\t\t];\n\n";
    $output .= "\t\tforeach (\$filters as \$key => \$value) {\n";
    $output .= "\t\t\t\$args['meta_query'][] = [\n";
    $output .= "\t\t\t\t'key'   => \$key,\n";
    $output .= "\t\t\t\t'value' => \$value\n";
    $output .= "\t\t\t];\n";
    $output .= "\t\t}\n\n";
    $output .= "\t\t\$posts = get_posts(\$args);\n\n";
    $output .= "\t\t\$objects = [];\n";
    $output .= "\t\tforeach (\$posts as \$post) {\n";
    $output .= "\t\t\t\$objects[] = new self(\$post->ID);\n";
    $output .= "\t\t}\n\n";
    $output .= "\t\treturn \$objects;\n";
    $output .= "\t}\n\n";
}

function generate_save_method(&$output, $property_names, $property_field_map)
{
    $output .= "\tpublic function save() {\n";
    $output .= "\t\t\$post_id = \$this->id;\n";

    // Update post data
    $output .= "\t\t\$post_data = [\n";
    $output .= "\t\t\t'ID' => \$post_id,\n";
    $output .= "\t\t\t'post_title' => \$this->title,\n";
    $output .= "\t\t\t'post_content' => \$this->content,\n";
    $output .= "\t\t\t'post_excerpt' => \$this->excerpt,\n";
    $output .= "\t\t\t'post_author' => \$this->author,\n";
    $output .= "\t\t];\n";
    $output .= "\t\twp_update_post(\$post_data);\n\n";

    // Update featured image
    $output .= "\t\tif (\$this->img) {\n";
    $output .= "\t\t\t\$attachment_id = attachment_url_to_postid(\$this->img);\n";
    $output .= "\t\t\tif (\$attachment_id) {\n";
    $output .= "\t\t\t\tset_post_thumbnail(\$post_id, \$attachment_id);\n";
    $output .= "\t\t\t}\n";
    $output .= "\t\t}\n";

    foreach ($property_names as $property_name) {
        if ($property_name != 'id' && $property_name != 'title' && $property_name != 'content' && $property_name != 'excerpt' && $property_name != 'img' && $property_name != 'dateCreate' && $property_name != 'dateUpdate' && $property_name != 'author') {
            $meta_key = $property_field_map[$property_name];
            $output .= "\t\tif (\$this->$property_name !== null) {\n";
            $output .= "\t\t\tupdate_post_meta(\$post_id, '$meta_key', \$this->$property_name);\n";
            $output .= "\t\t}\n";
        }
    }

    $output .= "\t}\n\n";
}


function generate_delete_method(&$output)
{
    $output .= "\tpublic function delete() {\n";
    $output .= "\t\twp_delete_post(\$this->id, true);\n";
    $output .= "\t}\n\n";
}

function generate_get_posts_count_method(&$output, $post_type)
{
    $output .= "\tpublic static function getCount() {\n";
    $output .= "\t\t\$count_posts = wp_count_posts('$post_type');\n";
    $output .= "\t\treturn (int) \$count_posts->publish;\n";
    $output .= "\t}\n\n";
}

function generate_get_posts_count_by_meta_method(&$output, $post_type)
{
    $output .= "\tpublic static function getCountByMeta(\$meta_key, \$meta_value) {\n";
    $output .= "\t\t\$args = [\n";
    $output .= "\t\t\t'post_type'      => '$post_type',\n";
    $output .= "\t\t\t'posts_per_page' => -1,\n";
    $output .= "\t\t\t'meta_query'     => [\n";
    $output .= "\t\t\t\t[\n";
    $output .= "\t\t\t\t\t'key'   => \$meta_key,\n";
    $output .= "\t\t\t\t\t'value' => \$meta_value\n";
    $output .= "\t\t\t\t]\n";
    $output .= "\t\t\t]\n";
    $output .= "\t\t];\n\n";
    $output .= "\t\t\$posts = get_posts(\$args);\n\n";
    $output .= "\t\treturn count(\$posts);\n";
    $output .= "\t}\n\n";
}

function generate_filter_method(&$output, $post_type)
{
    $output .= "\tpublic static function filter(\$filters = [], \$taxonomies = [], \$posts_per_page = 10, \$page_number = 1, \$orderby = 'date', \$order = 'DESC') {\n";
    $output .= "\t\t\$args = [\n";
    $output .= "\t\t\t'post_type'      => '$post_type',\n";
    $output .= "\t\t\t'posts_per_page' => \$posts_per_page,\n";
    $output .= "\t\t\t'paged'          => \$page_number,\n";
    $output .= "\t\t\t'orderby'        => \$orderby,\n";
    $output .= "\t\t\t'order'          => \$order,\n";
    $output .= "\t\t\t'meta_query'     => [],\n";
    $output .= "\t\t\t'tax_query'      => []\n";
    $output .= "\t\t];\n\n";
    $output .= "\t\tforeach (\$filters as \$key => \$value) {\n";
    $output .= "\t\t\tif (is_array(\$value) && isset(\$value['compare']) && isset(\$value['value'])) {\n";
    $output .= "\t\t\t\t\$args['meta_query'][] = [\n";
    $output .= "\t\t\t\t\t'key'     => \$key,\n";
    $output .= "\t\t\t\t\t'value'   => \$value['value'],\n";
    $output .= "\t\t\t\t\t'compare' => \$value['compare']\n";
    $output .= "\t\t\t\t];\n";
    $output .= "\t\t\t} else {\n";
    $output .= "\t\t\t\t\$args['meta_query'][] = [\n";
    $output .= "\t\t\t\t\t'key'   => \$key,\n";
    $output .= "\t\t\t\t\t'value' => \$value\n";
    $output .= "\t\t\t\t];\n";
    $output .= "\t\t\t}\n";
    $output .= "\t\t}\n\n";
    $output .= "\t\tforeach (\$taxonomies as \$taxonomy => \$terms) {\n";
    $output .= "\t\t\t\$args['tax_query'][] = [\n";
    $output .= "\t\t\t\t'taxonomy' => \$taxonomy,\n";
    $output .= "\t\t\t\t'field'    => 'term_id',\n";
    $output .= "\t\t\t\t'terms'    => \$terms\n";
    $output .= "\t\t\t];\n";
    $output .= "\t\t}\n\n";
    $output .= "\t\t\$posts = get_posts(\$args);\n\n";
    $output .= "\t\t\$objects = [];\n";
    $output .= "\t\tforeach (\$posts as \$post) {\n";
    $output .= "\t\t\t\$objects[] = new self(\$post->ID);\n";
    $output .= "\t\t}\n\n";
    $output .= "\t\treturn \$objects;\n";
    $output .= "\t}\n\n";
}


function boonband_post_type_class_generator_admin_page()
{
    // Check if the user is allowed to access the page.
    if (!current_user_can('manage_options')) {
        return;
    }

    // Add the admin page HTML and form.
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post">
            <?php wp_nonce_field('boonband_post_type_class_generator'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="post_type">Post Type</label></th>
                    <td>
                        <select name="post_type" id="post_type">
                            <?php
                            $post_types = get_post_types(['public' => true], 'names');
                            foreach ($post_types as $post_type) {
                                echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Generate Class">
            </p>
        </form>
    </div>
    <?php
}

function boonband_post_type_class_generator_admin_menu()
{
    add_submenu_page(
        'tools.php',
        'Boon.Band Post Type Class Generator',
        'Post Type Class Generator',
        'manage_options',
        'boonband-post-type-class-generator',
        'boonband_post_type_class_generator_admin_page'
    );
}

add_action('admin_menu', 'boonband_post_type_class_generator_admin_menu');

// Handle form submission using admin_init hook
function boonband_post_type_class_generator_handle_form_submission_init()
{
    if (!isset($_POST['submit'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!wp_verify_nonce($_POST['_wpnonce'], 'boonband_post_type_class_generator')) {
        die('Invalid request.');
    }

    $post_type = sanitize_text_field($_POST['post_type']);
    $generated_class = generate_post_type_class($post_type);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $post_type . '_class.php"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    echo $generated_class;
    exit;
}

add_action('admin_init', 'boonband_post_type_class_generator_handle_form_submission_init');
