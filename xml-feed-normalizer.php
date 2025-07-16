<?php
/**
 * Plugin Name: XML Feed Normalizer
 * Description: Reformat XML Feeds from <item1>..</item1> <item2>..</item2> to <item>..</item> <item>..</item>
 * Version: 1.3.7
 * Plugin URI: https://github.com/WeAreCode045/xml-feed-normalizer
 * Author: WeAreCode045
 * Author URI: https://github.com/WeAreCode045
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xml-feed-normalizer
 */

register_activation_hook(__FILE__, 'xfn_schedule_cron');
register_deactivation_hook(__FILE__, 'xfn_clear_cron');

function xfn_schedule_cron() {
    if (!wp_next_scheduled('xfn_cron_hook')) {
        wp_schedule_event(time(), 'twicedaily', 'xfn_cron_hook');
    }
}

function xfn_clear_cron() {
    wp_clear_scheduled_hook('xfn_cron_hook');
}

// CRON handler
add_action('xfn_cron_hook', 'xfn_process_all_feeds');
function xfn_process_all_feeds() {
    $feeds = get_option('xfn_feeds', []);
    foreach ($feeds as $id => $url) {
        xfn_convert_feed($id, $url);
    }
}

// Admin menu
add_action('admin_menu', function () {
    add_menu_page('XML Feed Normalizer', 'Feed Normalizer', 'manage_options', 'xml-feed-normalizer', 'xfn_admin_page');
});

// Adminpagina
function xfn_admin_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['xfn_new_feed'])) {
            $feeds = get_option('xfn_feeds', []);
            $id = 'feed_' . time();
            $feeds[$id] = esc_url_raw($_POST['xfn_new_feed']);
            update_option('xfn_feeds', $feeds);
            xfn_convert_feed($id, $feeds[$id]);
        }

        if (!empty($_POST['xfn_delete_feed'])) {
            $feeds = get_option('xfn_feeds', []);
            $id = sanitize_text_field($_POST['xfn_delete_feed']);
            unset($feeds[$id]);
            update_option('xfn_feeds', $feeds);
        }
    }

    $feeds = get_option('xfn_feeds', []);
    echo '<div class="wrap"><h1>XML Feed Normalizer</h1>';
    echo '<form method="post">
            <input type="url" name="xfn_new_feed" placeholder="Feed URL toevoegen" style="width: 50%;" required>
            <button class="button button-primary">Toevoegen</button>
          </form><br>';

    if (!empty($feeds)) {
        echo '<table class="widefat"><thead><tr><th>Originele URL</th><th>Output URL</th><th>Acties</th></tr></thead><tbody>';
        foreach ($feeds as $id => $url) {
            $converted = xfn_get_feed_output_url($id);
            echo '<tr>
                    <td><a href="'.esc_url($url).'" target="_blank">'.esc_html($url).'</a></td>
                    <td><a href="'.esc_url($converted).'" target="_blank">'.esc_html($converted).'</a></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="xfn_delete_feed" value="'.esc_attr($id).'">
                            <button class="button">Verwijderen</button>
                        </form>
                    </td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Er zijn nog geen feeds toegevoegd.</p>';
    }

    echo '</div>';
}

function xfn_get_feed_output_path($id) {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . "/xfn-{$id}.xml";
}

function xfn_get_feed_output_url($id) {
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'] . "/xfn-{$id}.xml";
}

function xfn_convert_feed($id, $url) {
    $originalXml = @file_get_contents($url);
    if (!$originalXml) return false;
    
    // Load the original XML
    $xml = @simplexml_load_string($originalXml);
    if (!$xml) return false;
    
    // Get the root element name
    $rootElementName = $xml->getName();
    
    // Create a new XML document with proper structure
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    
    // Create root element
    $rootElement = $dom->createElement($rootElementName);
    $dom->appendChild($rootElement);
    
    // Process each node, converting item0, item1, etc. to item
    foreach ($xml->children() as $key => $node) {
        if (preg_match('/^item\d+$/', $key)) {
            // Create a new item element
            $itemElement = $dom->createElement('item');
            $rootElement->appendChild($itemElement);
            
            // Convert SimpleXML node to DOM node for processing
            $simpleXmlDom = dom_import_simplexml($node);
            
            // Copy all child elements and attributes from the original node
            foreach ($node->children() as $childName => $childNode) {
                $childElement = $dom->createElement($childName, htmlspecialchars((string)$childNode));
                $itemElement->appendChild($childElement);
                
                // Copy attributes if any
                foreach ($childNode->attributes() as $attrName => $attrValue) {
                    $childElement->setAttribute($attrName, (string)$attrValue);
                }
            }
            
            // Copy attributes of the item itself if any
            foreach ($node->attributes() as $attrName => $attrValue) {
                $itemElement->setAttribute($attrName, (string)$attrValue);
            }
        }
    }
    
    file_put_contents(xfn_get_feed_output_path($id), $dom->saveXML());
    return true;
}
?>
