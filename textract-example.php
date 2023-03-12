<?php
/**
 *  textract-example.php
 *  Demonstrates how to access AWD Textract to process an image
 *  and retrieve the key value pairs.
 * 
 * @author Terry Dunevent
 * @version 1.0
 * @license https://opensource.org/license/gpl-3-0/ GNU Public License
 */

require '/var/www/common/aws/vendor/autoload.php';

use Aws\Credentials\CredentialProvider;

use Aws\Textract\TextractClient;

$provider = CredentialProvider::env();

$provider = CredentialProvider::env();

$provider = CredentialProvider::env();

$client = new TextractClient([
	
    'region' => 'us-west-2',
	'version' => 'latest',
    'http' => [
        'timeout' => 10,
        'connect_timeout' => 5
    ]
]);

// The file in this project.
$filename = "test-name-only.png";
$file = fopen($filename, "rb");
$contents = fread($file, filesize($filename));
fclose($file);
$options = [
    'Document' => [
		'Bytes' => $contents
    ],
    'FeatureTypes' => ["FORMS"], 
];

$result = $client->analyzeDocument($options);

if(!isset($result['Blocks'])) {
    die('unable to get the blocks array');
}

$blocks = $result['Blocks'];

$key_map = [];

$value_map = [];

$block_map = [];

foreach ($blocks as $block) {
	
    $block_id = $block['Id'];
    
    $block_map[$block_id] = $block; 
    
    if($block['BlockType'] == "KEY_VALUE_SET") {
            
        if(in_array('KEY', $block['EntityTypes'])) {
            $key_map[$block_id] = $block;
        }
           
        else {
            $value_map[$block_id] = $block;
        }
    }
}

$kvs = get_kv_relationship($key_map, $value_map, $block_map);

echo '<pre>';
print_r($kvs);


/**
 * Gets the relationship between a key and a value
 * @param array $key_map
 * @param array $value_map
 * @param array $block_map
 * @returns array $key_values
 */
function get_kv_relationship($key_map, $value_map, $block_map)
{
    $key_values = [];  
    foreach($key_map as $block_id=>$key_block) {
        $value_block = find_value_block($key_block, $value_map);
        $key = get_key($key_block, $block_map);
        $val = get_value($value_block, $block_map, 1);
        $key_values[trim($key)] = $val;
    }
    
    return $key_values;
}

/**
 * Gets a value block
 * @param array $key_block
 * @param array $value_map
 * returns array $value_block
 */
function find_value_block($key_block, $value_map)
{
    $value_block = [];  
    foreach($key_block['Relationships'] as $relationship) {
            if($relationship['Type'] == 'VALUE') {
                foreach($relationship['Ids'] as $relationship_id) {
                    $value_block[] = $value_map[$relationship_id];    
                }
            }
        }
    
    return $value_block;
}

/**
 * Gets key text for a key/value pair
 * @param array $key_block
 * @param array $value_block
 * @returns string $key
 */
function get_key($key_block, $block_map)
{   
    $key = '';
    if(isset($key_block['Relationships'])) {
        foreach ($key_block['Relationships'] as $relationship) {
            if ($relationship['Type'] == 'CHILD') {
                foreach($relationship['Ids'] as $child_id) {
                    $word = $block_map[$child_id];
                    if($word['BlockType'] == 'WORD') {
                        $key .= $word['Text'] . ' ';
                    }
                } 
            }   
        }
    }

    return $key;
}

/**
 * Gets value text for a key/value pair
 * @param array $value_block
 * @param array $block_map
  * @returns string $value
 */
function get_value($value_block, $block_map)
{  
    $value= '';
    foreach($value_block as $r=>$v) {
        if(isset($v['Relationships'])) {
            foreach ($v['Relationships'] as $relationship) {
                if ($relationship['Type'] == 'CHILD') {
                    foreach($relationship['Ids'] as $child_id) {
                        $word = $block_map[$child_id];
                        if($word['BlockType'] == 'WORD') {
                            $value .= $word['Text'] . ' ';
                        }
                        if($word['BlockType'] == 'SELECTION_ELEMENT' && $word['SelectionStatus'] == "SELECTED") { 
                            $value .= 'X';
                        }
                    } 
                }   
            }
        }
    }

    return $value;
}