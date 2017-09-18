<?php
/**
 * Generate documentation for hooks
 * Credit: https://github.com/woothemes/woocommerce/blob/master/apigen/hook-docs.php
 */
class Hooks_Parser {

	private static $current_file           = '';
	private static $custom_hooks_found     = '';

	public static function process_hooks( $all_files ) {
		$scanned = array();

		self::$custom_hooks_found = array();

		echo '<pre>';

		foreach ( $all_files as $f ) {
			if ( ! is_file( $f ) ) {
				continue;
			}

			self::$current_file = str_replace( ABSPATH, '', $f );
			$tokens             = token_get_all( file_get_contents( $f ) );
			$token_type         = false;
			$current_class      = '';
			$current_function   = '';

			if ( in_array( self::$current_file, $scanned, true ) ) {
				continue;
			}

			$scanned[] = self::$current_file;

			foreach ( $tokens as $index => $token ) {
				if ( is_array( $token ) ) {
					if ( $token[0] == T_CLASS ) {
						$token_type = 'class';
					} elseif ( $token[0] == T_FUNCTION ) {
						$token_type = 'function';
					} elseif ( $token[1] === 'do_action' || $token[1] === 'do_action_ref_array' ) {
						$token_type = 'action';
					} elseif ( $token[1] === 'apply_filters' || $token[1] === 'apply_filters_ref_array' ) {
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
								if ( is_array( $token )
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
								}// End foreach().

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
									}// End while().
								}// End if().

								if ( '$hook' === $hook ) {
									break;
								}
								// if ( '$hook' === $hook ) {
								// 	echo '<xmp>'. __LINE__ .') $details: '. print_r( array(
								// 		'line'     => $token[2],
								// 		'class'    => $current_class,
								// 		'function' => $current_function,
								// 		'file'     => array( self::$current_file ),
								// 		'type'     => $token_type
								// 	), true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $f: '. print_r( $f, true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index - 3]: '. print_r( $tokens[ $index - 3], true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index - 2]: '. print_r( $tokens[ $index - 2], true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index - 1]: '. print_r( $tokens[ $index - 1], true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $token: '. print_r( $token, true ) .'</xmp>';
								// 	// echo '<xmp>'. __LINE__ .') $token: '. print_r( 'T_DOC_COMMENT' == token_name( $token[0] ), true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index + 1]: '. print_r( $tokens[ $index + 1], true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index + 2]: '. print_r( $tokens[ $index + 2], true ) .'</xmp>';
								// 	echo '<xmp>'. __LINE__ .') $tokens[ $index + 3]: '. print_r( $tokens[ $index + 3], true ) .'</xmp>';
								// 	wp_die( '<xmp>'. __LINE__ .') $hook: '. print_r( $hook, true ) .'</xmp>' );
								// }
								if ( isset( self::$custom_hooks_found[ $hook ] ) ) {
									self::$custom_hooks_found[ $hook ]['file'][] = self::$current_file;
								} else {
									self::$custom_hooks_found[ $hook ] = array(
									'line'     => $token[2],
									'class'    => $current_class,
									'function' => $current_function,
									'file'     => array( self::$current_file ),
									'type'     => $token_type,
									);
								}
							break;
						}// End switch().
						$token_type = false;
					}// End if().
				}// End if().
			}// End foreach().
		}// End foreach().

		ksort( self::$custom_hooks_found );

		$triggers = array();
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
							$contents .= rtrim( $value, '{' ) . '${' . ( $key + 1 ) . ':\\\\$';
						} else {
							$contents .= $value;
						}
					}
				}

				$count++;
				$triggers[] = '{"trigger": "' . str_replace( "'", '', $trigger ) . '", "contents": "' . $contents . '" },';

				$contents = 'add_' . $details['type'] . "( '" . $contents . "'";
				$two = ++$key;
				$one = ++$key;
				$contents .= ', ${' . $one . ':\'${' . $two . ':callback}\'}';
				$contents .= '${' . ( ++$key ) . '}';
				$contents .= ' );';

				$triggers[] = '{"trigger": "' . ( 'action' === $details['type'] ? 'aa_' : 'af_' ) . str_replace( "'", '', $trigger ) . '", "contents": "' . $contents . '" },';

			}// End if().
		}// End foreach().

		echo "Hooks (". $count . "):<br>";
		echo rtrim( implode( '<br>', $triggers ), ',' );

		die;
	}
}
