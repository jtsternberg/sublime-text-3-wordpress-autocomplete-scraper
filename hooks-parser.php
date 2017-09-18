<?php
/**
 * Generate documentation for hooks
 * Credit: https://github.com/woothemes/woocommerce/blob/master/apigen/hook-docs.php
 */
class Hooks_Parser {

	private $current_file           = '';
	private static $custom_hooks_found     = '';
	private $debug = false;

	public static function process_hooks( $all_files ) {
		( new self() )->_process_hooks( $all_files );
	}

	protected function __construct() {
		self::$custom_hooks_found = array();
	}

	protected function _process_hooks( $all_files ) {
		$scanned = array();

		self::$custom_hooks_found = array();

		echo '<pre>';

		foreach ( $all_files as $f ) {
			if ( ! is_file( $f ) ) {
				continue;
			}

			$this->current_file = str_replace( ABSPATH, '', $f );
			$this->tokens       = token_get_all( file_get_contents( $f ) );
			$token_type         = false;
			$current_class      = '';
			$current_function   = '';

			if ( in_array( $this->current_file, $scanned, true ) ) {
				continue;
			}

			$scanned[] = $this->current_file;

			foreach ( $this->tokens as $this->index => $token ) {
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

								$this->hook = trim( $token[1], "'" );
								$this->loop = 0;

								$next_next_bit = false;

								// $this->debug = 386 == $token[0] && 3977 == $token[2];
								// $debug = false;

								foreach ( array( '_', '-' ) as $separator ) {
									if ( $separator !== substr( $this->hook, '-1', 1 ) ) {
										continue;
									}

									$this->build_hook_name_with_separators( $separator );
								}// End foreach().

								if ( '{' === $this->hook ) {
									$this->build_hook_name();
								}// End if().

								$this->hook = str_replace( array( "'", '"' ), '', $this->hook );

								if (
									empty( $this->hook )
									|| in_array( $current_class, array(
										'WP_Hook',
									), true )
									|| in_array( $current_function, array(
										'add_action',
										'did_action',
										'has_filter',
										'do_action_ref_array',
										'apply_filters_ref_array',
										'do_action_deprecated',
										'apply_filters_deprecated',
									), true )
									|| in_array( $this->hook, array(
										'$hook',
										'$page_hook',
										'{$field}',
									), true )
								) {
									break;
								}
								if ( in_array( $this->hook, array(
									'{{$field}',
									'$args',
									'$page_hook',
									'$tag',
									'$value',
								), true ) /*|| 0 === strpos( $this->hook, '{$old_status}' )*/ ) {
									echo '<xmp>'. __LINE__ .') $details: '. print_r( array(
										'hook'            => $this->hook,
										'line'            => $token[2],
										'class'           => $current_class,
										'function'        => $current_function,
										'file'            => array( $this->current_file ),
										'$next_next_bit' => $next_next_bit,
									), true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $f: '. print_r( $f, true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index - 3]: '. print_r( $this->tokens[ $this->index - 3], true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index - 2]: '. print_r( $this->tokens[ $this->index - 2], true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index - 1]: '. print_r( $this->tokens[ $this->index - 1], true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $token: '. print_r( $token, true ) .'</xmp>';
									// echo '<xmp>'. __LINE__ .') $token: '. print_r( 'T_DOC_COMMENT' == token_name( $token[0] ), true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index + 1]: '. print_r( $this->tokens[ $this->index + 1], true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index + 2]: '. print_r( $this->tokens[ $this->index + 2], true ) .'</xmp>';
									echo '<xmp>'. __LINE__ .') $this->tokens[ $this->index + 3]: '. print_r( $this->tokens[ $this->index + 3], true ) .'</xmp>';
									echo '<hr/>';
									echo '<br/>';
									// wp_die( '<xmp>'. __LINE__ .') $this->hook: '. print_r( $this->hook, true ) .'</xmp>' );
								}

								if ( isset( self::$custom_hooks_found[ $this->hook ] ) ) {
									self::$custom_hooks_found[ $this->hook ]['file'][] = $this->current_file;
								} else {
									self::$custom_hooks_found[ $this->hook ] = array(
									'line'     => $token[2],
									'class'    => $current_class,
									'function' => $current_function,
									'file'     => array( $this->current_file ),
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
		// wp_die( '<xmp>'. __LINE__ .') : '. print_r( array_keys( self::$custom_hooks_found ), true ) .'</xmp>' );

		$triggers = $trigger_names = array();
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

				$trigger = strtr( $trigger, array(
					'comment->comment'   => 'comment',
					'post->post'         => 'post',
					'user->user'         => 'user',
					'()->'               => '_',
					'()'                 => '_',
					'this->'             => '',
					'->'                 => '_',
					'get_current_screen' => 'current_screen',
					'_REQUEST['          => '_REQUEST_',
					'_GET['              => '_GET_',
					'{$'                 => '&lt;',
					'{'                  => '&lt;',
					'}'                  => '&gt;',
					']'                  => '',
					"'"                  => '',
				) );

				$trigger_names[] = $trigger;

				$triggers[] = '{"trigger": "' . $trigger . '", "contents": "' . $contents . '" },';

				$contents = 'add_' . $details['type'] . "( '" . $contents . "'";
				$two = ++$key;
				$one = ++$key;
				$contents .= ', ${' . $one . ':\'${' . $two . ':callback}\'}';
				$contents .= '${' . ( ++$key ) . '}';
				$contents .= ' );';

				$triggers[] = '{"trigger": "' . ( 'action' === $details['type'] ? 'aa_' : 'af_' ) . $trigger . '", "contents": "' . $contents . '" },';

			}// End if().
		}// End foreach().

		// foreach ( $trigger_names as $trigger_name ) {
		// 	echo '<xmp>'. print_r( $trigger_name, true ) .'</xmp>';
		// }
		// echo '<br/>';
		// echo '<hr/>';
		// echo '<br/>';
		// wp_die( '<xmp>'. __LINE__ .') : '. print_r( 'test', true ) .'</xmp>' );

		echo "Hooks (". $count . "):<br>";
		echo rtrim( implode( '<br>', $triggers ), ',' );

		die;
	}

	protected function build_hook_name_with_separators( $separator ) {
		static $counter = 0;
		$this->loop = 0;
		$this->hook .= '{';
		$open = true;
		// Keep adding to hook until we find a comma or colon
		while ( 1 ) {
			$d = $this->loop( array( '.', '{', '}', '"', "'", ' ' ), $this->debug ? __FUNCTION__ . $counter : false );
			if ( ! $d ) {
				continue;
			}

			if ( in_array( $d['next'], array( ',', ';' ), true ) ) {
				if ( $open ) {
					$this->hook .= '}';
					$open = false;
				}
				break;
			}

			if ( ')' === $d['next'] ) {
				if ( in_array( $d['next_next'], array( ',', ';' ), true ) ) {
					if ( $open ) {
						$this->hook .= '}';
						$open = false;
					}
					break;
				}
			}

			if (
				'$' === $d['first']
				&& in_array( $d['next_next'], array( '->', '}' ), true )
				&& '{' !== substr( $this->hook, -1, 1 )
			) {
				echo '<xmp>'. __LINE__ .') $this->hook: '. print_r( $this->hook, true ) .'</xmp>';
				$this->hook .= '{';
				$open = true;
			}

			if ( $separator === $d['first'] && '->' !== substr( $d['next'], 0, 2 ) ) {
				$d['next'] = '}' . $d['next'];
				$open = false;
			}

			if ( $separator === $d['last'] ) {
				$d['next'] .= '{';
				$open = true;
			}

			$this->hook .= $d['next'];
		}
	}

	protected function build_hook_name() {
		static $counter = 0;

		$open = true;
		// Keep adding to hook until we find a comma or colon
		while ( 1 ) {
			$d = $this->loop( array( '.', '{', '"', "'", ' ' ), $this->debug ? __FUNCTION__ . $counter++ : false );
			if ( ! $d ) {
				continue;
			}

			if ( '}' === $d['next'] ) {
				$this->hook .= '}';
				continue;
			}

			if ( in_array( $d['next'], array( ',', ';' ), true ) ) {
				if ( $open ) {
					// $this->hook .= '}';
					$open = false;
				}
				break;
			}

			if ( ')' === $d['next'] ) {
				if ( in_array( $d['next_next'], array( ',', ';' ), true ) ) {
					if ( $open ) {
						$open = false;
					}
					break;
				}
			}

			if ( '$' === $d['first'] && '{' !== substr( $this->hook, '-1', 1 ) /*&& in_array( $d['next_next'], array( '->', '$' ), true )*/ ) {
				$this->hook .= '{';
				$open = true;
			}

			$this->hook .= $d['next'];
		}// End while().

	}

	protected function loop( $skip_list, $debug ) {
		$this->loop++;

		$next = $this->get_token_string( $this->index + $this->loop );

		if ( in_array( $next, $skip_list, true ) ) {
			return false;
		}

		$next_next = $this->get_token_string( $this->index + $this->loop + 1 );
		$first     = substr( $next, 0, 1 );
		$last      = substr( $next, -1, 1 );

		$d = compact( 'first', 'last', 'next', 'next_next' );

		if ( $debug ) {
			echo '<xmp>'. __LINE__ .') ' . $debug . ': '. print_r( array(
				'$this->hook' => $this->hook,
				'$d' => $d,
			), true ) .'</xmp>';
		}

		return $d;
	}


	public function get_token_string( $key ) {
		return trim( trim( is_string( $this->tokens[ $key ] ) ? $this->tokens[ $key ] : $this->tokens[ $key ][1], '"' ), "'" );
	}
}
