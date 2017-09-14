<?php
/**
 * Generate documentation for hooks
 * Credit: https://github.com/woothemes/woocommerce/blob/master/apigen/hook-docs.php
 */
class Hooks_Parser {
	private static $current_file           = '';
	private static $files_to_scan          = array();
	private static $pattern_custom_actions = '/do_action(.*?);/i';
	private static $pattern_custom_filters = '/apply_filters(.*?);/i';
	private static $found_files            = array();
	private static $custom_hooks_found     = '';

	private static function get_files( $pattern, $flags = 0, $path = '' ) {

	    if ( ! $path && ( $dir = dirname( $pattern ) ) != '.' ) {

	        if ($dir == '\\' || $dir == '/') { $dir = ''; } // End IF Statement

	        return self::get_files(basename( $pattern ), $flags, $dir . '/' );

	    } // End IF Statement
	    $paths = glob( $path . '*', GLOB_ONLYDIR | GLOB_NOSORT );
	    $files = glob( $path . $pattern, $flags );

	    if ( is_array( $paths ) ) {
		    foreach ( $paths as $p ) {
			    $found_files = array();
		   		$retrieved_files = (array) self::get_files( $pattern, $flags, $p . '/' );
		   		foreach ( $retrieved_files as $file ) {
			   		if ( ! in_array( $file, self::$found_files ) )
			   			$found_files[] = $file;
		   		}

		   		self::$found_files = array_merge( self::$found_files, $found_files );

		   		if ( is_array( $files ) && is_array( $found_files ) ) {
		   			$files = array_merge( $files, $found_files );
		   		}

		    } // End FOREACH Loop
	    }
	    return $files;
    }

	private static function get_hook_link( $hook, $details = array() ) {
		if ( ! empty( $details['class'] ) ) {
			$link = 'https://cmb2.io/api//source-class-' . $details['class'] . '.html#' . $details['line'];
		} elseif ( ! empty( $details['function'] ) ) {
			$link = 'https://cmb2.io/api//source-function-' . $details['function'] . '.html#' . $details['line'];
		} else {
			$link = 'https://github.com/CMB2/CMB2/search?utf8=%E2%9C%93&q=' . $hook;
		}

		if ( false !== strpos( $hook, '{' ) || false !== strpos( $hook, '$' ) ) {
			$hook = '"'. $hook .'"';
		} else {
			$hook = "'$hook'";
		}

		return '<a href="' . $link . '">' . $hook . '</a>';
	}

	public static function process_hooks( $class_files ) {
		self::$files_to_scan = array(
			'Hooks' => $class_files,
		);

		$scanned = array();

		// ob_start();

		// echo '<div id="content">';
		// echo '<h1>WordPress Action and Filter Hook Reference</h1>';

		foreach ( self::$files_to_scan as $heading => $files ) {
			self::$custom_hooks_found = array();

			foreach ( $files as $f ) {
				if ( ! is_file( $f ) ) {
					continue;
				}

				self::$current_file = basename( $f );
				$tokens             = token_get_all( file_get_contents( $f ) );
				$token_type         = false;
				$current_class      = '';
				$current_function   = '';

				if ( in_array( self::$current_file, $scanned ) ) {
					continue;
				}

				$scanned[] = self::$current_file;

				foreach ( $tokens as $index => $token ) {
					if ( is_array( $token ) ) {
						if ( $token[0] == T_CLASS ) {
							$token_type = 'class';
						} elseif ( $token[0] == T_FUNCTION ) {
							$token_type = 'function';
						} elseif ( $token[1] === 'do_action' ) {
							$token_type = 'action';
						} elseif ( $token[1] === 'apply_filters' ) {
							$token_type = 'filter';
						} elseif ( $token_type && ! empty( trim( $token[1] ) ) ) {
							switch ( $token_type ) {
								case 'class' :
									$current_class = $token[1];
								break;
								case 'function' :
									$current_function = $token[1];
								break;
								case 'filter' :
								case 'action' :
									if (
										is_array( $token )
										&& is_int( $token[0] )
										&& 'T_DOC_COMMENT' == token_name( $token[0] )
									) {
										break;
									}

									if ( in_array( $current_function, array( 'sanitize_bookmark_field' ), true ) ) {
										break;
									}

									$hook = trim( $token[1], "'" );
									$loop = 0;

									foreach ( array( '_', '-' ) as $separator ) {
										if ( $separator !== substr( $hook, '-1', 1 ) ) {
											continue;
										}

										$hook .= '{';
										$open = true;
										// Keep adding to hook until we find a comma or colon
										while ( 1 ) {
											$loop ++;
											$next_hook  = trim( trim( is_string( $tokens[ $index + $loop ] ) ? $tokens[ $index + $loop ] : $tokens[ $index + $loop ][1], '"' ), "'" );

											if ( in_array( $next_hook, array( '.', '{', '}', '"', "'", ' ' ) ) ) {
												continue;
											}

											$hook_first = substr( $next_hook, 0, 1 );
											$hook_last  = substr( $next_hook, -1, 1 );

											if ( in_array( $next_hook, array( ',', ';' ), true ) ) {
												if ( $open ) {
													$hook .= '}';
													$open = false;
												}
												break;
											}

											if ( $separator === $hook_first && '->' !== substr( $next_hook, 0, 2 ) ) {
												$next_hook = '}' . $next_hook;
												$open = false;
											}

											if ( $separator === $hook_last ) {
												$next_hook .= '{';
												$open = true;
											}

											$hook .= $next_hook;
											// echo '<xmp>$hook: '. print_r( $hook, true ) .'</xmp>';
										}
									}

									if ( '{' === $hook ) {
										$open = true;
										// Keep adding to hook until we find a comma or colon
										while ( 1 ) {
											$loop ++;
											$next_hook = trim( trim( is_string( $tokens[ $index + $loop ] ) ? $tokens[ $index + $loop ] : $tokens[ $index + $loop ][1], '"' ), "'" );

											if ( '}' === $next_hook ) {
												$hook .= '}';
												continue;
											}

											if ( in_array( $next_hook, array( '.', '{', '"', "'", ' ' ) ) ) {
												continue;
											}

											$hook_first = substr( $next_hook, 0, 1 );
											$hook_last  = substr( $next_hook, -1, 1 );

											if ( in_array( $next_hook, array( ',', ';' ), true ) ) {
												if ( $open ) {
													// $hook .= '}';
													$open = false;
												}
												break;
											}

											if ( $separator === $hook_first && '->' !== substr( $next_hook, 0, 2 ) ) {
												$next_hook = '}' . $next_hook;
												$open = false;
											}

											if ( $separator === $hook_last ) {
												$next_hook .= '{';
												$open = true;
											}

											$hook .= $next_hook;
											// echo '<xmp>$hook: '. print_r( $hook, true ) .'</xmp>';
										}
									}

									if ( '{' === $hook ) {
										echo '<xmp>'. __LINE__ .') $details: '. print_r( array(
											'line'     => $token[2],
											'class'    => $current_class,
											'function' => $current_function,
											'file'     => array( self::$current_file ),
											'type'     => $token_type
										), true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $f: '. print_r( $f, true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index - 3]: '. print_r( $tokens[ $index - 3], true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index - 2]: '. print_r( $tokens[ $index - 2], true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index - 1]: '. print_r( $tokens[ $index - 1], true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $token: '. print_r( $token, true ) .'</xmp>';
										// echo '<xmp>'. __LINE__ .') $token: '. print_r( 'T_DOC_COMMENT' == token_name( $token[0] ), true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index + 1]: '. print_r( $tokens[ $index + 1], true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index + 2]: '. print_r( $tokens[ $index + 2], true ) .'</xmp>';
										echo '<xmp>'. __LINE__ .') $tokens[ $index + 3]: '. print_r( $tokens[ $index + 3], true ) .'</xmp>';
										wp_die( '<xmp>'. __LINE__ .') $hook: '. print_r( $hook, true ) .'</xmp>' );
									}
									if ( isset( self::$custom_hooks_found[ $hook ] ) ) {
										self::$custom_hooks_found[ $hook ]['file'][] = self::$current_file;
									} else {
    									self::$custom_hooks_found[ $hook ] = array(
											'line'     => $token[2],
											'class'    => $current_class,
											'function' => $current_function,
											'file'     => array( self::$current_file ),
											'type'     => $token_type
										);
									}
								break;
							}
							$token_type = false;
						}
					}
				}
			}
			// die( '<xmp>self::$custom_hooks_found: '. print_r( self::$custom_hooks_found, true ) .'</xmp>' );

			// foreach ( self::$custom_hooks_found as $hook => $details ) {
			// 	if ( ! strstr( $hook, 'cmb2' ) ) {
			// 		unset( self::$custom_hooks_found[ $hook ] );
			// 	}
			// }

			ksort( self::$custom_hooks_found );

			$count = 0;
			foreach ( self::$custom_hooks_found as $hook => $details ) {
				if ( $hook && ! in_array( $hook, array( '$args', '$page_hook', '$tag', '$value' ) ) ) {
					$trigger = trim( $hook, "\n\r\t " );
					$trigger = str_replace( '"', "'", $trigger );
					$trigger = str_replace( "'", '', $trigger );
					$contents = $trigger;
					$die = false;
					$key = 0;
					if ( false !== strpos( $trigger, '$' ) ) {
						$contents = '';
						foreach ( explode( '$', $trigger ) as $key => $value ) {
							if ( 0 === strpos( strrev( $value ), '{' ) ) {
								$contents .= rtrim( $value, '{' ) . '${'. ( $key + 1 ) . ':\\\\$';
							} else {
								$contents .= $value;
							}
							// if ( 0 === strpos( $value, '}' ) ) {
							// 	$contents .= rtrim( $value, '{' ) . '\\${1:\\$';
							// }
							// "add_option_${1:\\$option}"
						}
						$die = true;
							// wp_die( '<xmp>'. __LINE__ .') : '. print_r( compact( 'hook', 'details', 'trigger', 'key', 'value', 'contents' ), true ) .'</xmp>' );
					}

					// $contents = str_replace( '$', '\\\$', $trigger );
					// $trigger = '${' . ($key+1) . ':' .  esc_html( $trigger ) . '}';

					$count++;
					// print( '<xmp>'. __LINE__ .') $trigger: '. print_r( $trigger, true ) .'</xmp>' );
					// wp_die( '<xmp>'. __LINE__ .') $details: '. print_r( $details, true ) .'</xmp>' );
					echo '{"trigger": "'. str_replace( "'", '', $trigger ) .'", "contents": "'. $contents .'" },' . "<br>";

					$contents = "add_" . $details['type'] ."( '" . $contents . "'";
					$two = ++$key;
					$one = ++$key;
					$contents .= ', ${'. $one . ':\'${'. $two . ':callback}\'}';
					$contents .= '${'. ( ++$key ) . '}';
					$contents .= " );";

					echo '{"trigger": "'. ( 'action' === $details['type'] ? 'aa_' : 'af_' ) . str_replace( "'", '', $trigger ) .'", "contents": "'. $contents .'" },' . "<br>";

					// wp_die( '<xmp>'. __LINE__ .') : '. print_r( $details, true ) .'</xmp>' );
					if ( $die ) {

						// wp_die( '<xmp>'. __LINE__ .') : '. print_r( compact( 'hook', 'details', 'trigger', 'key', 'value', 'contents' ), true ) .'</xmp>' );
					}
				}
			}

			die( '<xmp>'. __LINE__ .') $count: '. print_r( $count, true ) .'</xmp>' );
			// if ( ! empty( self::$custom_hooks_found ) ) {
			// 	$actions = self::wp_list_filter( self::$custom_hooks_found, array( 'type' => 'action' ) );
			// 	$filters = self::wp_list_filter( self::$custom_hooks_found, array( 'type' => 'filter' ) );

			// 	echo '<div class="panel panel-default"><div class="panel-heading"><h2>Action Hooks</h2></div>';

			// 	echo '<table class="summary table table-bordered table-striped"><thead><tr><th>Hook</th><th>File(s)</th></tr></thead><tbody>';

			// 	foreach ( $actions as $hook => $details ) {
			// 		echo '<tr>
			// 			<td>' . self::get_hook_link( $hook, $details ) . '</td>
			// 			<td>' . implode( ', ', array_unique( $details['file'] ) ) . '</td>
			// 		</tr>' . "\n";
			// 	}

			// 	echo '</tbody></table></div>';
			// 	echo '<div class="panel panel-default"><div class="panel-heading"><h2>Filter Hooks</h2></div>';

			// 	echo '<table class="summary table table-bordered table-striped"><thead><tr><th>Hook</th><th>File(s)</th></tr></thead><tbody>';

			// 	foreach ( $filters as $hook => $details ) {
			// 		echo '<tr>
			// 			<td>' . self::get_hook_link( $hook, $details ) . '</td>
			// 			<td>' . implode( ', ', array_unique( $details['file'] ) ) . '</td>
			// 		</tr>' . "\n";
			// 	}

			// 	echo '</tbody></table></div>';
			// }
		}

		// echo '</div><div id="footer">';

		// $docs = ob_get_clean();
		// wp_die( '<xmp>'. __LINE__ .') $docs: '. print_r( $docs, true ) .'</xmp>' );
		// file_put_contents( __DIR__ . '/hook-docs.html', $docs );
		echo "Hook docs generated :)\n";
	}

	/**
	 * Filters a list of objects, based on a set of key => value arguments.
	 *
	 * @since 3.1.0
	 *
	 * @param array  $list     An array of objects to filter.
	 * @param array  $args     Optional. An array of key => value arguments to match
	 *                         against each object. Default empty array.
	 * @param string $operator Optional. The logical operation to perform. 'AND' means
	 *                         all elements from the array must match. 'OR' means only
	 *                         one element needs to match. 'NOT' means no elements may
	 *                         match. Default 'AND'.
	 * @return array Array of found values.
	 */
	protected static function wp_list_filter( $list, $args = array(), $operator = 'AND' ) {
		if ( ! is_array( $list ) )
			return array();

		if ( empty( $args ) )
			return $list;

		$operator = strtoupper( $operator );
		$count = count( $args );
		$filtered = array();

		foreach ( $list as $key => $obj ) {
			$to_match = (array) $obj;

			$matched = 0;
			foreach ( $args as $m_key => $m_value ) {
				if ( array_key_exists( $m_key, $to_match ) && $m_value == $to_match[ $m_key ] )
					$matched++;
			}

			if ( ( 'AND' == $operator && $matched == $count )
			  || ( 'OR' == $operator && $matched > 0 )
			  || ( 'NOT' == $operator && 0 == $matched ) ) {
				$filtered[$key] = $obj;
			}
		}

		return $filtered;
	}
}
