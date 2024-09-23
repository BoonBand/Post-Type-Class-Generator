<?php
/*
Plugin Name: Enhanced Post Type Class Generator by Boon.Band
Plugin URI: https://github.com/BoonBand/Post-Type-Class-Generator
Description: Generate a PHP class based on a WordPress post type with options for lazy loading, magic methods, explicit getters and setters, strict typing, relationships, serialization, static factory methods, detailed documentation, performance optimizations, and coding standards compliance.
Version: 2.1
Author: Boon.Band
Author URI: https://www.boon.band/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main plugin class.
 */
class BoonBand_Post_Type_Class_Generator
{
    /**
     * Initializes the plugin by adding hooks.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_boonband_generate_class', [$this, 'handle_form_submission']);
    }

    /**
     * Adds the plugin's admin page to the WordPress admin menu.
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'Boon.Band Post Type Class Generator',
            'Post Type Class Generator',
            'manage_options',
            'boonband-post-type-class-generator',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Renders the plugin's admin page.
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html('Post Type Class Generator'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('boonband_post_type_class_generator'); ?>
                <input type="hidden" name="action" value="boonband_generate_class">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="post_type">Post Type</label></th>
                        <td>
                            <select name="post_type" id="post_type">
                                <?php
                                foreach ($post_types as $post_type_obj) {
                                    echo '<option value="' . esc_attr($post_type_obj->name) . '">' . esc_html($post_type_obj->labels->singular_name) . ' (' . esc_html($post_type_obj->name) . ')</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="use_magic_methods">Use Magic Methods</label></th>
                        <td>
                            <input type="checkbox" name="use_magic_methods" id="use_magic_methods" value="1" checked>
                            <label for="use_magic_methods">Yes</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="generate_tests">Generate Unit Tests</label></th>
                        <td>
                            <input type="checkbox" name="generate_tests" id="generate_tests" value="1">
                            <label for="generate_tests">Yes</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Generate Class'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handles the form submission and generates the class file.
     */
    public function handle_form_submission()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user', 'Error', ['response' => 403]);
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'boonband_post_type_class_generator')) {
            wp_die('Invalid nonce specified', 'Error', ['response' => 403]);
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $generate_tests = isset($_POST['generate_tests']) ? true : false;
        $use_magic_methods = isset($_POST['use_magic_methods']) ? true : false;

        // Validate the post type
        if (!post_type_exists($post_type)) {
            wp_die('Invalid post type specified', 'Error', ['response' => 400]);
        }

        $options = [
            'generate_tests' => $generate_tests,
            'use_magic_methods' => $use_magic_methods,
        ];
        $generated_class = $this->generate_post_type_class($post_type, $options);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($post_type) . '_class.php"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        echo $generated_class;
        exit;
    }

    /**
     * Generates a PHP class for a given post type.
     *
     * @param string $post_type The post type to generate the class for.
     * @param array  $options   Optional settings for class generation.
     * @return string The generated PHP class code.
     */
    private function generate_post_type_class(string $post_type, array $options = []): string
    {
        // Initialize arrays for meta fields, taxonomies, and validation rules
        $meta_fields = [];
        $taxonomies = [];
        $field_validations = [];

        // Check if ACF is installed and active
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(['post_type' => $post_type]);

            // Process ACF fields if field groups are available
            if (!empty($field_groups)) {
                foreach ($field_groups as $field_group) {
                    $fields = acf_get_fields($field_group['key']);
                    if ($fields) {
                        $meta_fields = array_merge($meta_fields, $fields);
                    }
                }
            }
        }

        // Get taxonomies associated with the post type
        $taxonomies = get_object_taxonomies($post_type, 'objects');

        $class_name = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $post_type)));

        $output = "<?php\n";
        $output .= "namespace BoonBand\\GeneratedClasses;\n\n";
        $output .= "use WP_Query;\n";
        $output .= "use WP_Post;\n";
        $output .= "\n";
        $output .= "/**\n";
        $output .= " * Class $class_name\n";
        $output .= " *\n";
        $output .= " * This class represents the '$post_type' post type.\n";
        $output .= " *\n";
        $output .= " * @package BoonBand\\GeneratedClasses\n";
        $output .= " */\n";
        $output .= "class $class_name\n{\n";

        // Implement properties with type hints
        $property_field_map = [];
        $field_types = [];
        $relationships = [];
        $property_names = $this->generate_properties(
            $output,
            $meta_fields,
            $property_field_map,
            $field_validations,
            $taxonomies,
            $field_types,
            $relationships
        );

        // Generate constructor with type hints
        $this->generate_constructor(
            $output,
            $post_type
        );

        // Check if magic methods should be used
        if ($options['use_magic_methods']) {
            // Generate magic methods __get and __set
            $this->generate_magic_methods(
                $output,
                $property_names,
                $property_field_map,
                $field_types,
                $taxonomies,
                $relationships
            );
        } else {
            // Generate explicit getters and setters
            $this->generate_getters_and_setters(
                $output,
                $property_names,
                $property_field_map,
                $field_types,
                $taxonomies,
                $relationships
            );
        }

        // Generate methods
        $this->generate_methods(
            $output,
            $class_name,
            $post_type,
            $property_names,
            $property_field_map,
            $field_types,
            $taxonomies,
            $relationships
        );

        // Close the class
        $output .= "}\n";

        // Optionally generate unit tests
        if (!empty($options['generate_tests'])) {
            $test_output = $this->generate_unit_tests($class_name, $post_type);
            $output .= $test_output;
        }

        return $output;
    }

    /**
     * Generates the class properties based on meta fields and taxonomies.
     *
     * @param string &$output            The output string to append to.
     * @param array  $meta_fields        The meta fields to generate properties for.
     * @param array  &$property_field_map Map of property names to field names.
     * @param array  &$field_validations Map of property names to validation rules.
     * @param array  $taxonomies         The taxonomies associated with the post type.
     * @param array  &$field_types       Map of property names to their types.
     * @param array  &$relationships     List of relationships.
     * @return array The list of property names.
     */
    private function generate_properties(
        string &$output,
        array $meta_fields,
        array &$property_field_map,
        array &$field_validations,
        array $taxonomies,
        array &$field_types,
        array &$relationships
    ): array {
        $property_names = [
            'id',
            'postType',
        ];

        // Generate default properties
        $output .= "    /**\n";
        $output .= "     * Post ID.\n";
        $output .= "     *\n";
        $output .= "     * @var int\n";
        $output .= "     */\n";
        $output .= "    private int \$id;\n\n";

        $output .= "    /**\n";
        $output .= "     * Post type.\n";
        $output .= "     *\n";
        $output .= "     * @var string\n";
        $output .= "     */\n";
        $output .= "    private string \$postType;\n\n";

        $output .= "    /**\n";
        $output .= "     * Loaded properties tracker.\n";
        $output .= "     *\n";
        $output .= "     * @var array\n";
        $output .= "     */\n";
        $output .= "    private array \$loadedProperties = [];\n\n";

        // Generate properties from meta fields
        foreach ($meta_fields as $field) {
            if (!empty($field['name']) && $field['type'] != 'tab') {
                $property_name = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $field['name']))));
                $property_name = str_replace('.', '', $property_name);

                // Ensure unique property names
                $original_property_name = $property_name;
                $counter = 1;
                while (in_array($property_name, $property_names)) {
                    $property_name = $original_property_name . $counter;
                    $counter++;
                }
                $property_names[] = $property_name;
                $property_field_map[$property_name] = $field['name'];

                // Store validation rules
                $field_validations[$property_name] = $field;

                // Determine property type based on field type
                $property_type = $this->get_php_type_from_field_type($field['type']);
                $field_types[$property_name] = $property_type;

                // Check for relationships
                if (in_array($field['type'], ['post_object', 'relationship', 'page_link'])) {
                    $relationships[$property_name] = $field;
                }

                // Adjust nullable type
                if ($property_type === 'mixed') {
                    $nullable_type = 'mixed';
                } else {
                    $nullable_type = "?{$property_type}";
                }

                // Property DocBlock
                $output .= "    /**\n";
                $output .= "     * {$field['label']} ({$field['type']}).\n";
                $output .= "     *\n";
                $output .= "     * @var {$property_type}|null\n";
                $output .= "     */\n";
                $output .= "    private {$nullable_type} \${$property_name} = null;\n\n";
            }
        }

        // Additional standard WordPress post fields
        $standard_fields = [
            'title' => 'string',
            'content' => 'string',
            'excerpt' => 'string',
            'img' => 'string',
            'dateCreate' => '\\DateTime',
            'dateUpdate' => '\\DateTime',
            'author' => 'int',
        ];
        foreach ($standard_fields as $field => $type) {
            if ($type === 'mixed' || $type == '' || $type === null) {
                $nullable_type = 'mixed';
            } else {
                $nullable_type = "?{$type}";
            }

            $output .= "    /**\n";
            $output .= "     * " . ucfirst($field) . ".\n";
            $output .= "     *\n";
            $output .= "     * @var {$type}|null\n";
            $output .= "     */\n";
            $output .= "    private {$nullable_type} \${$field} = null;\n\n";
            $property_names[] = $field;
            $field_types[$field] = $type;
        }

        // Taxonomy properties
        foreach ($taxonomies as $taxonomy) {
            $property_name = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $taxonomy->name))));
            $original_property_name = $property_name;
            $counter = 1;
            while (in_array($property_name, $property_names)) {
                $property_name = $original_property_name . $counter;
                $counter++;
            }
            $property_names[] = $property_name;
            $property_field_map[$property_name] = $taxonomy->name;
            $field_types[$property_name] = 'array';

            // Property DocBlock
            $output .= "    /**\n";
            $output .= "     * Terms for taxonomy '{$taxonomy->label}'.\n";
            $output .= "     *\n";
            $output .= "     * @var array|null\n";
            $output .= "     */\n";
            $output .= "    private ?array \${$property_name} = null;\n\n";
        }

        return $property_names;
    }

    /**
     * Generates the class constructor.
     *
     * @param string &$output   The output string to append to.
     * @param string $post_type The post type.
     */
    private function generate_constructor(
        string &$output,
        string $post_type
    ) {
        // Generate constructor
        $output .= "    /**\n";
        $output .= "     * $post_type constructor.\n";
        $output .= "     *\n";
        $output .= "     * @param int \$post_id Post ID.\n";
        $output .= "     * @throws \\Exception If invalid post ID or post type.\n";
        $output .= "     */\n";
        $output .= "    public function __construct(int \$post_id)\n    {\n";
        $output .= "        \$this->id = \$post_id;\n";
        $output .= "        \$this->postType = '" . esc_js($post_type) . "';\n";
        $output .= "        \$post = get_post(\$this->id);\n";
        $output .= "        if (!\$post || \$post->post_type !== \$this->postType) {\n";
        $output .= "            throw new \\Exception('Invalid post ID or post type.');\n";
        $output .= "        }\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates magic methods __get and __set for property access.
     *
     * @param string &$output             The output string to append to.
     * @param array  $property_names      The list of property names.
     * @param array  $property_field_map  Map of property names to field names.
     * @param array  $field_types         Map of property names to their types.
     * @param array  $taxonomies          The taxonomies associated with the post type.
     * @param array  $relationships       List of relationships.
     */
    private function generate_magic_methods(
        string &$output,
        array $property_names,
        array $property_field_map,
        array $field_types,
        array $taxonomies,
        array $relationships
    ) {
        // Generate __get method
        $output .= "    /**\n";
        $output .= "     * Magic method to get properties lazily.\n";
        $output .= "     *\n";
        $output .= "     * @param string \$name Property name.\n";
        $output .= "     * @return mixed\n";
        $output .= "     * @throws \\Exception If property does not exist.\n";
        $output .= "     */\n";
        $output .= "    public function __get(string \$name)\n    {\n";
        $output .= "        if (!property_exists(\$this, \$name)) {\n";
        $output .= "            throw new \\Exception(\"Property '\$name' does not exist.\");\n";
        $output .= "        }\n\n";
        $output .= "        if (\$this->\$name === null && !isset(\$this->loadedProperties[\$name])) {\n";
        $output .= "            \$this->loadProperty(\$name);\n";
        $output .= "        }\n\n";
        $output .= "        return \$this->\$name;\n";
        $output .= "    }\n\n";

        // Generate __set method
        $output .= "    /**\n";
        $output .= "     * Magic method to set properties with validation.\n";
        $output .= "     *\n";
        $output .= "     * @param string \$name  Property name.\n";
        $output .= "     * @param mixed  \$value Value to set.\n";
        $output .= "     * @throws \\Exception If property does not exist or validation fails.\n";
        $output .= "     */\n";
        $output .= "    public function __set(string \$name, \$value): void\n    {\n";
        $output .= "        if (!property_exists(\$this, \$name)) {\n";
        $output .= "            throw new \\Exception(\"Property '\$name' does not exist.\");\n";
        $output .= "        }\n\n";
        $output .= "        // Validate value\n";
        $output .= "        \$this->validateField(\$value, '{$field_types[$name]}');\n";
        $output .= "        \$this->\$name = \$value;\n";
        $output .= "        \$this->loadedProperties[\$name] = true;\n";
        $output .= "    }\n\n";

        // Generate loadProperty method
        $output .= "    /**\n";
        $output .= "     * Loads a property value lazily.\n";
        $output .= "     *\n";
        $output .= "     * @param string \$property_name Property name to load.\n";
        $output .= "     * @throws \\Exception If post is invalid.\n";
        $output .= "     */\n";
        $output .= "    private function loadProperty(string \$property_name): void\n    {\n";
        $output .= "        switch (\$property_name) {\n";

        foreach ($property_names as $property_name) {
            $output .= "            case '{$property_name}':\n";
            if ($property_name === 'id' || $property_name === 'postType') {
                $output .= "                // Already set in constructor\n";
            } elseif (in_array($property_name, ['title', 'content', 'excerpt', 'dateCreate', 'dateUpdate', 'author'])) {
                $output .= "                \$post = get_post(\$this->id);\n";
                switch ($property_name) {
                    case 'title':
                        $output .= "                \$this->{$property_name} = \$post->post_title;\n";
                        break;
                    case 'content':
                        $output .= "                \$this->{$property_name} = \$post->post_content;\n";
                        break;
                    case 'excerpt':
                        $output .= "                \$this->{$property_name} = \$post->post_excerpt;\n";
                        break;
                    case 'dateCreate':
                        $output .= "                \$this->{$property_name} = new \\DateTime(\$post->post_date);\n";
                        break;
                    case 'dateUpdate':
                        $output .= "                \$this->{$property_name} = new \\DateTime(\$post->post_modified);\n";
                        break;
                    case 'author':
                        $output .= "                \$this->{$property_name} = (int)\$post->post_author;\n";
                        break;
                }
            } elseif ($property_name === 'img') {
                $output .= "                \$this->img = get_the_post_thumbnail_url(\$this->id) ?: '';\n";
            } elseif (array_key_exists($property_name, $property_field_map)) {
                $field_name = $property_field_map[$property_name];
                if (isset($relationships[$property_name])) {
                    $output .= "                // Load relationship\n";
                    $output .= "                \$this->{$property_name} = get_field('" . esc_attr($field_name) . "', \$this->id);\n";
                } else {
                    $output .= "                \$this->{$property_name} = get_field('" . esc_attr($field_name) . "', \$this->id);\n";
                }
            } else {
                // Check if property is a taxonomy
                if (in_array($property_name, array_keys($property_field_map))) {
                    $taxonomy_name = $property_field_map[$property_name];
                    $output .= "                \$this->{$property_name} = wp_get_post_terms(\$this->id, '$taxonomy_name', ['fields' => 'all']);\n";
                } else {
                    $output .= "                // Unknown property\n";
                }
            }
            $output .= "                \$this->loadedProperties['{$property_name}'] = true;\n";
            $output .= "                break;\n";
        }

        $output .= "            default:\n";
        $output .= "                throw new \\Exception(\"Property '\$property_name' cannot be loaded.\");\n";
        $output .= "        }\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates explicit getters and setters for property access.
     *
     * @param string &$output             The output string to append to.
     * @param array  $property_names      The list of property names.
     * @param array  $property_field_map  Map of property names to field names.
     * @param array  $field_types         Map of property names to their types.
     * @param array  $taxonomies          The taxonomies associated with the post type.
     * @param array  $relationships       List of relationships.
     */
    private function generate_getters_and_setters(
        string &$output,
        array $property_names,
        array $property_field_map,
        array $field_types,
        array $taxonomies,
        array $relationships
    ) {
        // Generate getters and setters
        foreach ($property_names as $property_name) {
            $method_suffix = ucfirst($property_name);
            $property_type = $field_types[$property_name];

            // Generate getter
            $output .= "    /**\n";
            $output .= "     * Gets the value of {$property_name}.\n";
            $output .= "     *\n";
            $output .= "     * @return {$property_type}|null\n";
            $output .= "     * @throws \\Exception If property cannot be loaded.\n";
            $output .= "     */\n";
            $output .= "    public function get{$method_suffix}(): ?{$property_type}\n    {\n";
            $output .= "        if (\$this->{$property_name} === null && !isset(\$this->loadedProperties['{$property_name}'])) {\n";
            $output .= "            \$this->loadProperty('{$property_name}');\n";
            $output .= "        }\n";
            $output .= "        return \$this->{$property_name};\n";
            $output .= "    }\n\n";

            // Generate setter
            if ($property_name !== 'id' && $property_name !== 'postType') {
                $output .= "    /**\n";
                $output .= "     * Sets the value of {$property_name}.\n";
                $output .= "     *\n";
                $output .= "     * @param {$property_type} \$value\n";
                $output .= "     * @throws \\Exception If validation fails.\n";
                $output .= "     */\n";
                $output .= "    public function set{$method_suffix}({$property_type} \$value): void\n    {\n";
                $output .= "        \$this->validateField(\$value, '{$property_type}');\n";
                $output .= "        \$this->{$property_name} = \$value;\n";
                $output .= "        \$this->loadedProperties['{$property_name}'] = true;\n";
                $output .= "    }\n\n";
            }
        }

        // Generate loadProperty method
        $this->generate_load_property_method(
            $output,
            $property_names,
            $property_field_map,
            $field_types,
            $taxonomies,
            $relationships
        );
    }

    /**
     * Generates the loadProperty method used in getters.
     *
     * @param string &$output             The output string to append to.
     * @param array  $property_names      The list of property names.
     * @param array  $property_field_map  Map of property names to field names.
     * @param array  $field_types         Map of property names to their types.
     * @param array  $taxonomies          The taxonomies associated with the post type.
     * @param array  $relationships       List of relationships.
     */
    private function generate_load_property_method(
        string &$output,
        array $property_names,
        array $property_field_map,
        array $field_types,
        array $taxonomies,
        array $relationships
    ) {
        // Generate loadProperty method
        $output .= "    /**\n";
        $output .= "     * Loads a property value lazily.\n";
        $output .= "     *\n";
        $output .= "     * @param string \$property_name Property name to load.\n";
        $output .= "     * @throws \\Exception If property cannot be loaded.\n";
        $output .= "     */\n";
        $output .= "    private function loadProperty(string \$property_name): void\n    {\n";
        $output .= "        switch (\$property_name) {\n";

        foreach ($property_names as $property_name) {
            $output .= "            case '{$property_name}':\n";
            if ($property_name === 'id' || $property_name === 'postType') {
                $output .= "                // Already set in constructor\n";
            } elseif (in_array($property_name, ['title', 'content', 'excerpt', 'dateCreate', 'dateUpdate', 'author'])) {
                $output .= "                \$post = get_post(\$this->id);\n";
                switch ($property_name) {
                    case 'title':
                        $output .= "                \$this->{$property_name} = \$post->post_title;\n";
                        break;
                    case 'content':
                        $output .= "                \$this->{$property_name} = \$post->post_content;\n";
                        break;
                    case 'excerpt':
                        $output .= "                \$this->{$property_name} = \$post->post_excerpt;\n";
                        break;
                    case 'dateCreate':
                        $output .= "                \$this->{$property_name} = new \\DateTime(\$post->post_date);\n";
                        break;
                    case 'dateUpdate':
                        $output .= "                \$this->{$property_name} = new \\DateTime(\$post->post_modified);\n";
                        break;
                    case 'author':
                        $output .= "                \$this->{$property_name} = (int)\$post->post_author;\n";
                        break;
                }
            } elseif ($property_name === 'img') {
                $output .= "                \$this->img = get_the_post_thumbnail_url(\$this->id) ?: '';\n";
            } elseif (array_key_exists($property_name, $property_field_map)) {
                $field_name = $property_field_map[$property_name];
                if (isset($relationships[$property_name])) {
                    $output .= "                // Load relationship\n";
                    $output .= "                \$this->{$property_name} = get_field('" . esc_attr($field_name) . "', \$this->id);\n";
                } else {
                    $output .= "                \$this->{$property_name} = get_field('" . esc_attr($field_name) . "', \$this->id);\n";
                }
            } else {
                // Check if property is a taxonomy
                if (in_array($property_name, array_keys($property_field_map))) {
                    $taxonomy_name = $property_field_map[$property_name];
                    $output .= "                \$this->{$property_name} = wp_get_post_terms(\$this->id, '$taxonomy_name', ['fields' => 'all']);\n";
                } else {
                    $output .= "                // Unknown property\n";
                }
            }
            $output .= "                \$this->loadedProperties['{$property_name}'] = true;\n";
            $output .= "                break;\n";
        }

        $output .= "            default:\n";
        $output .= "                throw new \\Exception(\"Property '\$property_name' cannot be loaded.\");\n";
        $output .= "        }\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates additional methods for the class.
     *
     * @param string &$output            The output string to append to.
     * @param string $class_name         The name of the class.
     * @param string $post_type          The post type.
     * @param array  $property_names     The list of property names.
     * @param array  $property_field_map Map of property names to field names.
     * @param array  $field_types        Map of property names to their types.
     * @param array  $taxonomies         The taxonomies associated with the post type.
     * @param array  $relationships      List of relationships.
     */
    private function generate_methods(
        string &$output,
        string $class_name,
        string $post_type,
        array $property_names,
        array $property_field_map,
        array $field_types,
        array $taxonomies,
        array $relationships
    ) {
        // Generate static methods like create(), findByID(), getLatest(), filter(), getCount(), getCountByMeta()
        $this->generate_static_methods(
            $output,
            $class_name,
            $post_type
        );

        // Generate save method
        $this->generate_save_method(
            $output,
            $property_names,
            $property_field_map,
            $taxonomies,
            $relationships
        );

        // Generate delete method
        $this->generate_delete_method(
            $output
        );

        // Generate serialization methods
        $this->generate_serialization_methods(
            $output,
            $property_names
        );

        // Generate validation method
        $this->generate_validation_method(
            $output
        );
    }

    /**
     * Generates static factory methods like create(), findByID(), getLatest(), filter(), getCount(), getCountByMeta().
     *
     * @param string &$output     The output string to append to.
     * @param string $class_name  The name of the class.
     * @param string $post_type   The post type.
     */
    private function generate_static_methods(
        string &$output,
        string $class_name,
        string $post_type
    ) {
        // Generate create method
        $output .= "    /**\n";
        $output .= "     * Creates a new post of type '$post_type'.\n";
        $output .= "     *\n";
        $output .= "     * @param array \$data Associative array of post data.\n";
        $output .= "     * @return {$class_name}\n";
        $output .= "     * @throws \\Exception If creation fails.\n";
        $output .= "     */\n";
        $output .= "    public static function create(array \$data): {$class_name}\n    {\n";
        $output .= "        \$post_data = [\n";
        $output .= "            'post_type'   => '" . esc_js($post_type) . "',\n";
        $output .= "            'post_status' => 'publish',\n";
        $output .= "        ];\n";
        $output .= "        \$post_data = array_merge(\$post_data, \$data);\n\n";
        $output .= "        \$post_id = wp_insert_post(\$post_data);\n";
        $output .= "        if (is_wp_error(\$post_id)) {\n";
        $output .= "            throw new \\Exception('Failed to create post: ' . \$post_id->get_error_message());\n";
        $output .= "        }\n\n";
        $output .= "        return new self(\$post_id);\n";
        $output .= "    }\n\n";

        // Generate findByID method
        $output .= "    /**\n";
        $output .= "     * Finds a post by ID.\n";
        $output .= "     *\n";
        $output .= "     * @param int \$post_id Post ID.\n";
        $output .= "     * @return {$class_name}\n";
        $output .= "     * @throws \\Exception If post not found or invalid.\n";
        $output .= "     */\n";
        $output .= "    public static function findByID(int \$post_id): {$class_name}\n    {\n";
        $output .= "        \$post = get_post(\$post_id);\n";
        $output .= "        if (!\$post || \$post->post_type !== '" . esc_js($post_type) . "') {\n";
        $output .= "            throw new \\Exception('Post not found or invalid post type.');\n";
        $output .= "        }\n";
        $output .= "        return new self(\$post_id);\n";
        $output .= "    }\n\n";

        // Generate getLatest method
        $output .= "    /**\n";
        $output .= "     * Retrieves the latest posts.\n";
        $output .= "     *\n";
        $output .= "     * @param int    \$posts_per_page Number of posts per page.\n";
        $output .= "     * @param int    \$paged          Page number.\n";
        $output .= "     * @param string \$orderby        Order by field.\n";
        $output .= "     * @param string \$order          Order direction.\n";
        $output .= "     * @param array  \$filters        Meta query filters.\n";
        $output .= "     * @return array                 Array of {$class_name} objects.\n";
        $output .= "     */\n";
        $output .= "    public static function getLatest(int \$posts_per_page = 10, int \$paged = 1, string \$orderby = 'date', string \$order = 'DESC', array \$filters = []): array\n    {\n";
        $output .= "        \$args = [\n";
        $output .= "            'post_type'      => '" . esc_js($post_type) . "',\n";
        $output .= "            'posts_per_page' => \$posts_per_page,\n";
        $output .= "            'paged'          => \$paged,\n";
        $output .= "            'orderby'        => \$orderby,\n";
        $output .= "            'order'          => \$order,\n";
        $output .= "            'fields'         => 'ids',\n";
        $output .= "            'meta_query'     => []\n";
        $output .= "        ];\n\n";
        $output .= "        if (!empty(\$filters)) {\n";
        $output .= "            foreach (\$filters as \$key => \$value) {\n";
        $output .= "                \$args['meta_query'][] = [\n";
        $output .= "                    'key'     => \$key,\n";
        $output .= "                    'value'   => \$value,\n";
        $output .= "                    'compare' => '=',\n";
        $output .= "                ];\n";
        $output .= "            }\n";
        $output .= "        }\n\n";
        $output .= "        \$query = new WP_Query(\$args);\n\n";
        $output .= "        \$objects = [];\n";
        $output .= "        if (\$query->have_posts()) {\n";
        $output .= "            foreach (\$query->posts as \$post_id) {\n";
        $output .= "                try {\n";
        $output .= "                    \$objects[] = new self(\$post_id);\n";
        $output .= "                } catch (\\Exception \$e) {\n";
        $output .= "                    // Handle exception if necessary\n";
        $output .= "                }\n";
        $output .= "            }\n";
        $output .= "        }\n\n";
        $output .= "        return \$objects;\n";
        $output .= "    }\n\n";

        // Generate filter method
        $output .= "    /**\n";
        $output .= "     * Filters posts based on meta and taxonomy queries.\n";
        $output .= "     *\n";
        $output .= "     * @param array  \$filters     Meta query filters.\n";
        $output .= "     * @param array  \$tax_queries Taxonomy queries.\n";
        $output .= "     * @param int    \$posts_per_page Number of posts per page.\n";
        $output .= "     * @param int    \$paged          Page number.\n";
        $output .= "     * @param string \$orderby        Order by field.\n";
        $output .= "     * @param string \$order          Order direction.\n";
        $output .= "     * @return array                 Array of {$class_name} objects.\n";
        $output .= "     */\n";
        $output .= "    public static function filter(array \$filters = [], array \$tax_queries = [], int \$posts_per_page = 10, int \$paged = 1, string \$orderby = 'date', string \$order = 'DESC'): array\n    {\n";
        $output .= "        \$args = [\n";
        $output .= "            'post_type'      => '" . esc_js($post_type) . "',\n";
        $output .= "            'posts_per_page' => \$posts_per_page,\n";
        $output .= "            'paged'          => \$paged,\n";
        $output .= "            'orderby'        => \$orderby,\n";
        $output .= "            'order'          => \$order,\n";
        $output .= "            'fields'         => 'ids',\n";
        $output .= "            'meta_query'     => [],\n";
        $output .= "            'tax_query'      => []\n";
        $output .= "        ];\n\n";
        $output .= "        if (!empty(\$filters)) {\n";
        $output .= "            foreach (\$filters as \$key => \$value) {\n";
        $output .= "                if (is_array(\$value) && isset(\$value['compare']) && isset(\$value['value'])) {\n";
        $output .= "                    \$args['meta_query'][] = [\n";
        $output .= "                        'key'     => \$key,\n";
        $output .= "                        'value'   => \$value['value'],\n";
        $output .= "                        'compare' => \$value['compare']\n";
        $output .= "                    ];\n";
        $output .= "                } else {\n";
        $output .= "                    \$args['meta_query'][] = [\n";
        $output .= "                        'key'     => \$key,\n";
        $output .= "                        'value'   => \$value,\n";
        $output .= "                        'compare' => '='\n";
        $output .= "                    ];\n";
        $output .= "                }\n";
        $output .= "            }\n";
        $output .= "        }\n\n";
        $output .= "        if (!empty(\$tax_queries)) {\n";
        $output .= "            foreach (\$tax_queries as \$taxonomy => \$terms) {\n";
        $output .= "                \$args['tax_query'][] = [\n";
        $output .= "                    'taxonomy' => \$taxonomy,\n";
        $output .= "                    'field'    => 'term_id',\n";
        $output .= "                    'terms'    => (array) \$terms\n";
        $output .= "                ];\n";
        $output .= "            }\n";
        $output .= "        }\n\n";
        $output .= "        \$query = new WP_Query(\$args);\n\n";
        $output .= "        \$objects = [];\n";
        $output .= "        if (\$query->have_posts()) {\n";
        $output .= "            foreach (\$query->posts as \$post_id) {\n";
        $output .= "                try {\n";
        $output .= "                    \$objects[] = new self(\$post_id);\n";
        $output .= "                } catch (\\Exception \$e) {\n";
        $output .= "                    // Handle exception if necessary\n";
        $output .= "                }\n";
        $output .= "            }\n";
        $output .= "        }\n\n";
        $output .= "        return \$objects;\n";
        $output .= "    }\n\n";

        // Generate getCount method
        $output .= "    /**\n";
        $output .= "     * Gets the count of published posts.\n";
        $output .= "     *\n";
        $output .= "     * @return int\n";
        $output .= "     */\n";
        $output .= "    public static function getCount(): int\n    {\n";
        $output .= "        \$count_posts = wp_count_posts('" . esc_js($post_type) . "');\n";
        $output .= "        return (int) \$count_posts->publish;\n";
        $output .= "    }\n\n";

        // Generate getCountByMeta method
        $output .= "    /**\n";
        $output .= "     * Gets the count of posts by meta key and value.\n";
        $output .= "     *\n";
        $output .= "     * @param string \$meta_key   Meta key.\n";
        $output .= "     * @param mixed  \$meta_value Meta value.\n";
        $output .= "     * @return int\n";
        $output .= "     */\n";
        $output .= "    public static function getCountByMeta(string \$meta_key, \$meta_value): int\n    {\n";
        $output .= "        \$args = [\n";
        $output .= "            'post_type'      => '" . esc_js($post_type) . "',\n";
        $output .= "            'posts_per_page' => -1,\n";
        $output .= "            'fields'         => 'ids',\n";
        $output .= "            'meta_query'     => [\n";
        $output .= "                [\n";
        $output .= "                    'key'   => \$meta_key,\n";
        $output .= "                    'value' => \$meta_value\n";
        $output .= "                ]\n";
        $output .= "            ]\n";
        $output .= "        ];\n\n";
        $output .= "        \$query = new WP_Query(\$args);\n";
        $output .= "        return (int) \$query->found_posts;\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates the save method for the class.
     *
     * @param string &$output            The output string to append to.
     * @param array  $property_names     The list of property names.
     * @param array  $property_field_map Map of property names to field names.
     * @param array  $taxonomies         The taxonomies associated with the post type.
     * @param array  $relationships      List of relationships.
     */
    private function generate_save_method(
        string &$output,
        array $property_names,
        array $property_field_map,
        array $taxonomies,
        array $relationships
    ) {
        $output .= "    /**\n";
        $output .= "     * Saves the post data.\n";
        $output .= "     *\n";
        $output .= "     * @throws \\Exception If save fails.\n";
        $output .= "     */\n";
        $output .= "    public function save(): void\n    {\n";
        $output .= "        \$post_id = \$this->id;\n\n";

        // Update post data
        $output .= "        \$post_data = [\n";
        $output .= "            'ID' => \$post_id,\n";
        $output .= "        ];\n\n";

        // Only update fields that have been loaded or set
        $output .= "        // Update standard fields if they have been loaded or set\n";
        $output .= "        if (isset(\$this->loadedProperties['title'])) {\n";
        $output .= "            \$post_data['post_title'] = \$this->title;\n";
        $output .= "        }\n";
        $output .= "        if (isset(\$this->loadedProperties['content'])) {\n";
        $output .= "            \$post_data['post_content'] = \$this->content;\n";
        $output .= "        }\n";
        $output .= "        if (isset(\$this->loadedProperties['excerpt'])) {\n";
        $output .= "            \$post_data['post_excerpt'] = \$this->excerpt;\n";
        $output .= "        }\n";
        $output .= "        if (isset(\$this->loadedProperties['author'])) {\n";
        $output .= "            \$post_data['post_author'] = \$this->author;\n";
        $output .= "        }\n\n";

        $output .= "        wp_update_post(\$post_data);\n\n";

        // Update featured image
        $output .= "        if (isset(\$this->loadedProperties['img']) && \$this->img) {\n";
        $output .= "            \$attachment_id = attachment_url_to_postid(\$this->img);\n";
        $output .= "            if (\$attachment_id) {\n";
        $output .= "                set_post_thumbnail(\$post_id, \$attachment_id);\n";
        $output .= "            }\n";
        $output .= "        }\n\n";

        // Update ACF fields
        $output .= "        // Update ACF fields\n";
        foreach ($property_field_map as $property_name => $field_name) {
            if (!isset($taxonomies[$field_name])) {
                $output .= "        if (isset(\$this->loadedProperties['$property_name'])) {\n";
                $output .= "            update_field('" . esc_attr($field_name) . "', \$this->$property_name, \$post_id);\n";
                $output .= "        }\n";
            }
        }

        // Update taxonomies
        $output .= "\n        // Update taxonomies\n";
        foreach ($taxonomies as $taxonomy) {
            $property_name = lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $taxonomy->name))));
            $output .= "        if (isset(\$this->loadedProperties['$property_name'])) {\n";
            $output .= "            wp_set_post_terms(\$post_id, \$this->$property_name, '" . $taxonomy->name . "');\n";
            $output .= "        }\n";
        }

        // Clear cache after saving
        $output .= "\n        // Clear loaded properties\n";
        $output .= "        \$this->loadedProperties = [];\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates the delete method for the class.
     *
     * @param string &$output The output string to append to.
     */
    private function generate_delete_method(
        string &$output
    ) {
        $output .= "    /**\n";
        $output .= "     * Deletes the post.\n";
        $output .= "     *\n";
        $output .= "     * @return bool True on success, false on failure.\n";
        $output .= "     */\n";
        $output .= "    public function delete(): bool\n    {\n";
        $output .= "        \$deleted = wp_delete_post(\$this->id, true);\n";
        $output .= "        return \$deleted ? true : false;\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates serialization methods like toArray() and toJSON().
     *
     * @param string &$output        The output string to append to.
     * @param array  $property_names The list of property names.
     */
    private function generate_serialization_methods(
        string &$output,
        array $property_names
    ) {
        // Generate toArray method
        $output .= "    /**\n";
        $output .= "     * Converts the object to an associative array.\n";
        $output .= "     *\n";
        $output .= "     * @return array\n";
        $output .= "     */\n";
        $output .= "    public function toArray(): array\n    {\n";
        $output .= "        \$data = [];\n";
        foreach ($property_names as $property_name) {
            $output .= "        \$data['{$property_name}'] = \$this->{$property_name};\n";
        }
        $output .= "        return \$data;\n";
        $output .= "    }\n\n";

        // Generate toJSON method
        $output .= "    /**\n";
        $output .= "     * Converts the object to a JSON string.\n";
        $output .= "     *\n";
        $output .= "     * @return string\n";
        $output .= "     */\n";
        $output .= "    public function toJSON(): string\n    {\n";
        $output .= "        return json_encode(\$this->toArray());\n";
        $output .= "    }\n\n";
    }

    /**
     * Generates a method to validate field values based on type.
     *
     * @param string &$output The output string to append to.
     */
    private function generate_validation_method(
        string &$output
    ) {
        $output .= "    /**\n";
        $output .= "     * Validates a field value based on its type.\n";
        $output .= "     *\n";
        $output .= "     * @param mixed  \$value The value to validate.\n";
        $output .= "     * @param string \$type  The expected type.\n";
        $output .= "     * @throws \\Exception If validation fails.\n";
        $output .= "     */\n";
        $output .= "    private function validateField(\$value, string \$type): void\n    {\n";
        $output .= "        if (\$type === 'int' && !is_int(\$value)) {\n";
        $output .= "            throw new \\Exception('Expected integer value.');\n";
        $output .= "        }\n";
        $output .= "        if (\$type === 'string' && !is_string(\$value)) {\n";
        $output .= "            throw new \\Exception('Expected string value.');\n";
        $output .= "        }\n";
        $output .= "        if (\$type === 'array' && !is_array(\$value)) {\n";
        $output .= "            throw new \\Exception('Expected array value.');\n";
        $output .= "        }\n";
        $output .= "        if (\$type === '\\DateTime' && !\$value instanceof \\DateTime) {\n";
        $output .= "            throw new \\Exception('Expected DateTime instance.');\n";
        $output .= "        }\n";
        $output .= "        // Add more type checks as needed\n";
        $output .= "    }\n\n";
    }

    /**
     * Maps ACF field types to PHP types.
     *
     * @param string $field_type The ACF field type.
     * @return string The corresponding PHP type.
     */
    private function get_php_type_from_field_type(string $field_type): string
    {
        switch ($field_type) {
            case 'text':
            case 'textarea':
            case 'wysiwyg':
            case 'email':
            case 'url':
            case 'password':
                return 'string';
            case 'number':
                return 'int';
            case 'true_false':
                return 'bool';
            case 'date_picker':
            case 'date_time_picker':
            case 'time_picker':
                return '\\DateTime';
            case 'file':
            case 'image':
                return 'string'; // URL or path
            case 'select':
            case 'checkbox':
            case 'gallery':
            case 'relationship':
            case 'repeater':
                return 'array';
            case 'post_object':
                return 'int'; // Post ID
            default:
                return 'mixed';
        }
    }

    /**
     * Generates unit tests for the class.
     *
     * @param string $class_name The name of the class.
     * @param string $post_type  The post type.
     * @return string The generated unit test code.
     */
    private function generate_unit_tests(string $class_name, string $post_type): string
    {
        $test_class_name = $class_name . 'Test';
        $output = "\n/**\n";
        $output .= " * Unit tests for $class_name.\n";
        $output .= " */\n";
        $output .= "class $test_class_name extends \\WP_UnitTestCase\n{\n";
        $output .= "    public function test_constructor()\n    {\n";
        $output .= "        // Create a dummy post\n";
        $output .= "        \$post_id = \$this->factory->post->create(['post_type' => '$post_type']);\n";
        $output .= "        \$obj = new $class_name(\$post_id);\n";
        $output .= "        \$this->assertInstanceOf('$class_name', \$obj);\n";
        $output .= "    }\n\n";
        $output .= "    // Add more tests as needed\n";
        $output .= "}\n";
        return $output;
    }
}

new BoonBand_Post_Type_Class_Generator();
