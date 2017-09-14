<?php
// Hooks_Parser
require_once __DIR__ . '/hooks-parser.php';

// function get_linksbyname($cat_name = "noname", $before = '', $after = '<br />', $between = " ", $show_images = true, $orderby = 'id',
// 						 $show_description = true, $show_rating = false,
// 						 $limit = -1, $show_updated = 0) {


function can_tokenize_classname( $class_name ) {
	if ( empty( $class_name ) ) {
		return false;
	}

	$blacklist = array(
		'MO',
		'PO',
	);

	foreach ( $blacklist as $blacklisted ) {
		// if ( 0 === strpos( $class_name, $blacklisted ) ) {
		if ( $class_name === $blacklisted ) {
			return false;
		}
	}

	if ( strlen( $class_name ) < 4 ) {
		return false;
	}

	return $class_name;
}

function get_constant_string( $tokens, $key ) {
	$constant_name = $tokens[ $key + 2 ][1];

	$constant_name = '';
	if ( isset( $tokens[ $key + 2 ][0] ) && is_int( $tokens[ $key + 2 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 2 ][0] ) ) {
		$constant_name = $tokens[ $key + 2 ][1];
	} elseif ( isset( $tokens[ $key + 3 ][0] ) && is_int( $tokens[ $key + 3 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 3 ][0] ) ) {
		$constant_name = $tokens[ $key + 3 ][1];
	} elseif ( isset( $tokens[ $key + 4 ][0] ) && is_int( $tokens[ $key + 4 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 4 ][0] ) ) {
		$constant_name = $tokens[ $key + 4 ][1];
	}

	$constant_name = str_replace( array( "'", '"' ), '', $constant_name );

	if ( strlen( $constant_name ) < 4 ) {
		return false;
	}

	if ( in_array( $constant_name, array( 'object', 'Error' ), true ) ) {
		return false;
	}

	return $constant_name;
}

function string_tokens( $tokens, $key ) {
	$string = '';

	if ( is_string( $tokens[ $key - 2 ] ) ) {
		$string .= $tokens[ $key - 2 ];
	}
	if ( isset( $tokens[ $key - 2 ][0] ) && is_int( $tokens[ $key - 2 ][0] ) && 'T_WHITESPACE' === token_name( $tokens[ $key - 2 ][0] ) ) {
		$string .= ' ';
	}

	if ( is_string( $tokens[ $key - 1 ] ) ) {
		$string .= $tokens[ $key - 1 ];
	}
	if ( isset( $tokens[ $key - 1 ][0] ) && is_int( $tokens[ $key - 1 ][0] ) && 'T_WHITESPACE' === token_name( $tokens[ $key - 1 ][0] ) ) {
		$string .= ' ';
	}

	$string .= 'define';
	if ( is_string( $tokens[ $key + 1 ] ) ) {
		$string .= $tokens[ $key + 1 ];
	}
	if ( isset( $tokens[ $key + 1 ][0] ) && is_int( $tokens[ $key + 1 ][0] ) && 'T_WHITESPACE' === token_name( $tokens[ $key + 1 ][0] ) ) {
		$string .= ' ';
	}

	if ( is_string( $tokens[ $key + 2 ] ) ) {
		$string .= $tokens[ $key + 2 ];
	}
	if ( isset( $tokens[ $key + 2 ][0] ) && is_int( $tokens[ $key + 2 ][0] ) && 'T_WHITESPACE' === token_name( $tokens[ $key + 2 ][0] ) ) {
		$string .= ' ';
	}
	if ( isset( $tokens[ $key + 2 ][0] ) && is_int( $tokens[ $key + 2 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 2 ][0] ) ) {
		$string .= $tokens[ $key + 2 ][1];
	} elseif ( isset( $tokens[ $key + 3 ][0] ) && is_int( $tokens[ $key + 3 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 3 ][0] ) ) {
		$string .= $tokens[ $key + 3 ][1];
	} elseif ( isset( $tokens[ $key + 4 ][0] ) && is_int( $tokens[ $key + 4 ][0] ) && 'T_CONSTANT_ENCAPSED_STRING' === token_name( $tokens[ $key + 4 ][0] ) ) {
		$string .= $tokens[ $key + 4 ][1];
	}

	return $string;
}
function tokenizeme() {
	if ( ! isset( $_GET['tokenizeme'] ) ) {
		return;
	}

    // get each entry
	$stack[] = rtrim(ABSPATH, "/");
	while ($stack) {
		$thisdir = array_pop($stack);
		if ($dircont = scandir($thisdir)) {
			$i=0;
			while (isset($dircont[$i])) {
				if ($dircont[$i] !== '.' && $dircont[$i] !== '..' && $dircont[$i] != "wp-content" ) {
					$current_file = "{$thisdir}/{$dircont[$i]}";
					if (is_file($current_file) &&strpos($dircont[$i], '.php') ) {
						$path[] = "{$thisdir}/{$dircont[$i]}";
					} elseif (is_dir($current_file) && $dircont[$i] != ".svn" ) {
						$path[] = "{$thisdir}/{$dircont[$i]}";
						$stack[] = $current_file;
					}
				}
				$i++;
			}
		}
	}


	if ( isset( $_GET['hooks'] ) ) {
		die( Hooks_Parser::process_hooks( $path ) );
	}

   echo '<pre>';
	$debug = false;
	$in_function = false;
	$in_class = false;
	$in_function_params = false;
	$function_params = '';
	$allclasses = array();
	$allconstants = array();
	foreach ( $path as $path_key => $file ) :
		if ( ! is_file($file) )
			continue;
		$source = file_get_contents($file);
		$tokens = token_get_all($source);
		$parenthesis_depth = 0;
		$braces_depth = 0;
		$in_function_params = false;

		// $debug = '/app/public/wp-includes/class-wp-error.php' === $file;
		// if ( ! $debug ) {
		// 	continue;
		// }
		// if ( '/app/public/wp-includes/class-wp-error.php' === $file ) {
		// 	wp_die( '<xmp>'. __LINE__ .') $tokens: '. print_r( $tokens, true ) .'</xmp>' );
		// }

		foreach( $tokens as $key => $token) :
			if ( is_array($token) ) {
				if ( 'define' === $token[1] ) {
					if ( isset( $tokens[ $key + 2 ][1], $tokens[ $key + 1 ] ) && '(' === $tokens[ $key + 1 ] ) {
						if ( $constant_name = get_constant_string( $tokens, $key ) ) {
							$allconstants[] = $constant_name;
						}
					}
				}

				if($debug):
					if(token_name($token[0]) != "T_WHITESPACE")
						echo token_name($token[0]) . ' ' . (($in_class)?'yes':'no') . '<BR>';
				endif;
                //switch the token name
				switch( token_name($token[0]) ) {
					case 'T_CURLY_OPEN' :
					$braces_depth++;
					break;
					case 'T_CLASS' :
					if ( isset( $tokens[ $key + 2 ][1] ) ) {
						$class_name = $tokens[ $key + 2 ][1];
						if ( can_tokenize_classname( $class_name ) ) {
							$allclasses[] = $class_name;
						}
					}
					$in_class = true;
					$brace_depth = 0;
					break;
					case 'T_FUNCTION' :
					$in_function = true;
					$got_function_name = false;
					break;
					case 'T_STRING' :
					if ( $in_function && !$got_function_name ) :
						$current_function = $token[1];
						$got_function_name = true;
						$in_function_params = true;
						continue 2;
					endif;
					case '' :
					break;
				}
				if( $in_function_params ) {
					$function_params .= $token[1];
				}
			} else {
				if( $in_function_params ) {
					if($debug) echo $token . '<BR>';
					switch($token) {
						case '(' :
						$parenthesis_depth++;
						continue 2;
						break;
						case ')' :
						if($parenthesis_depth)
							$parenthesis_depth--;
						if($parenthesis_depth)
							continue 2;
						$functions[] = array($current_function, trim($function_params), $in_class, $file );
						if($debug) var_dump(array($current_function, trim($function_params), $in_class, $file ) );
						$current_function = NULL;
						$function_params = NULL;
						$in_function_params = false;
						continue 2;
						break;
					}
					$function_params .= $token;
				} else {
					switch($token) {
						case '{' :
						$braces_depth++;
						continue 2;
						case '}' :
						$braces_depth--;
						if(!$braces_depth)
							$in_class = false;
						continue 2;
					}
				}
			}

		endforeach;
		$tokens = NULL;

	endforeach;

	foreach ( $functions as $key => $function ) {
		if($function[2]) {
			unset($functions[$key]);
			continue;
		}
		$args = array();
		if(!empty($function[1])) :
			$args = explode(',', $function[1]);
			foreach($args as $key => $arg) {
				$new_arg_text = trim($arg, "\n\r\t ");
				$new_arg_text = str_replace('"', "'", $new_arg_text);
				$new_arg_text = str_replace('$', '\\\$', $new_arg_text);
				$args[$key] = '${' . ($key+1) . ':' .  esc_html( $new_arg_text ) . '}';
			}
		endif;
		// if ( 'get_linksbyname' === $function[0] ) {

		// 	// function get_linksbyname($cat_name = "noname", $before = '', $after = '<br />', $between = " ", $show_images = true, $orderby = 'id',
		// 	// 						 $show_description = true, $show_rating = false,
		// 	// 						 $limit = -1, $show_updated = 0) {


		// 	echo "<br><br><br>" . '{"trigger": "'. $function[0] .'", "contents": "'. $function[0] .'( '. implode( ', ', $args ) .' )" },' . "<br>";

		// 		echo '<xmp>'. __LINE__ .') trigger: '. print_r( "\n" . '{"trigger": "'. $function[0] .'", "contents": "'. $function[0] .'( '. implode( ', ', $args ) .' )" },' . "<br>", true ) .'</xmp>';
		// 	echo '<xmp>'. __LINE__ .') $args: '. print_r( $args, true ) .'</xmp>';
		// 	die( '<xmp>'. __FUNCTION__ . ':' . __LINE__ .') '. print_r( $function, true ) .'</xmp>' );
		// }
		$args = implode( ', ', $args );
		if ( ! empty( $args ) ) {
			$args = ' ' . $args . ' ';
		}
		echo '{"trigger": "'. $function[0] .'", "contents": "'. $function[0] .'('. $args .')" },' . "\n";
	}

	// print( '<xmp>'. __LINE__ .') $allconstants: '. print_r( $allconstants, true ) .'</xmp>' );
	// print( '<xmp>'. __LINE__ .') $allclasses: '. print_r( $allclasses, true ) .'</xmp>' );

	echo '<xmp>'. __LINE__ .') all constants and classes: '. str_replace( "\n]", ",", print_r( output_clean_json_string( $allconstants ), true ) );
	echo str_replace( "\n[\n", '', print_r( output_clean_json_string( $allclasses ), true ) ) .'</xmp>';
	die;
}

function output_clean_json_string( $array ) {
	$array = array_unique( $array );
	asort( $array );
	$array = json_encode( array_values( $array ) );
	$array = str_replace( array( '"[', ']"', ',', ), array( '', '', ",\n\t" ), json_encode( $array ) );
	$array = "\t" . $array;
	$array = wp_unslash( $array );
	$array = "\n[\n" . $array . "\n]\n" ;

	return $array;
}
add_action( 'init', 'tokenizeme' );

