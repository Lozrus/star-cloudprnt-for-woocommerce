<?php
	mb_internal_encoding('utf-8');

	function star_mb_str_pad($str, $pad_len, $pad_str = ' ', $dir = STR_PAD_RIGHT) {
    $str_len = mb_strlen($str);
    $pad_str_len = mb_strlen($pad_str);
    if (!$str_len && ($dir == STR_PAD_RIGHT || $dir == STR_PAD_LEFT)) {
        $str_len = 1; // @debug
    }
    if (!$pad_len || !$pad_str_len || $pad_len <= $str_len) {
        return $str;
    }
   
    $result = null;
    $repeat = ceil($str_len - $pad_str_len + $pad_len);
    if ($dir == STR_PAD_RIGHT) {
        $result = $str . str_repeat($pad_str, $repeat);
        $result = mb_substr($result, 0, $pad_len);
    } else if ($dir == STR_PAD_LEFT) {
        $result = str_repeat($pad_str, $repeat) . $str;
        $result = mb_substr($result, -$pad_len);
    } else if ($dir == STR_PAD_BOTH) {
        $length = ($pad_len - $str_len) / 2;
        $repeat = ceil($length / $pad_str_len);
        $result = mb_substr(str_repeat($pad_str, $repeat), 0, floor($length))
                  	. $str
                    . mb_substr(str_repeat($pad_str, $repeat), 0, ceil($length));
    }
   
    return $result;
	}

	function star_cloudprnt_get_column_separated_data($columns, $max_chars)
	{
		$total_columns = count($columns);
		
		if ($total_columns == 0) return "";
		if ($total_columns == 1) return $columns[0];
		if ($total_columns == 2)
		{
			//$total_characters = strlen($columns[0])+strlen($columns[1]);
			$total_characters = mb_strwidth($columns[0]) + mb_strwidth($columns[1]);
			$total_whitespace = $max_chars - $total_characters;
			if ($total_whitespace < 0) return "";
			return $columns[0].str_repeat(" ", $total_whitespace).$columns[1];
		}
		
		$total_characters = 0;
		foreach ($columns as $column)
		{
			$total_characters += strlen($column);
		}
		$total_whitespace = $max_chars - $total_characters;
		if ($total_whitespace < 0) return "";
		$total_spaces = $total_columns-1;
		$space_width = floor($total_whitespace / $total_spaces);
		$result = $columns[0].str_repeat(" ", $space_width);
		for ($i = 1; $i < ($total_columns-1); $i++)
		{
			$result .= $columns[$i].str_repeat(" ", $space_width);
		}
		$result .= $columns[$total_columns-1];
		
		return $result;
	}
	
	function star_cloudprnt_get_seperator($max_chars)
	{
		//$max_chars = STAR_CLOUDPRNT_MAX_CHARACTERS_TWO_INCH;
		return str_repeat('_', $max_chars);
	}
	
	function star_cloudprnt_parse_order_status($status)
	{
		if ($status === 'wc-pending') return 'Pending Payment';
		else if ($status === 'wc-processing') return 'Processing';
		else if ($status === 'wc-on-hold') return 'On Hold';
		else if ($status === 'wc-completed') return 'Completed';
		else if ($status === 'wc-cancelled') return 'Cancelled';
		else if ($status === 'wc-refunded') return 'Refunded';
		else if ($status === 'wc-failed') return 'Failed';
		else return "Unknown";
	}
	
	function star_cloudprnt_get_wc_order_notes($order_id){
		//make sure it's a number
		$order_id = intval($order_id);
		//get the post 
		$post = get_post($order_id);
		//if there's no post, return as error
		if (!$post) return false;

		return $post->post_excerpt;
	}

	function star_cloudprnt_get_codepage_currency_symbol()
	{
		$encoding = get_option('star-cloudprnt-printer-encoding-select');
		$symbol = get_woocommerce_currency_symbol();

		return filterHTML($symbol);
	}
	
	function star_cloudprnt_get_formatted_variation($variation, $order, $item_id) 
	{
		$return = '';
		if (is_array($variation))
		{
			$variation_list = array();
			foreach ($variation as $name => $value)
			{
				// If the value is missing, get the value from the item
				if (!$value)
				{
					$meta_name = esc_attr(str_replace('attribute_', '', $name));
					$value = $order->get_item_meta($item_id, $meta_name, true);
				}

				// If this is a term slug, get the term's nice name
				if (taxonomy_exists(esc_attr(str_replace('attribute_', '', $name))))
				{
					$term = get_term_by('slug', $value, esc_attr(str_replace('attribute_', '', $name)));
					if (!is_wp_error($term) && ! empty($term->name))
					{
						$value = $term->name;
					}
				}
				else
				{
					$value = ucwords(str_replace( '-', ' ', $value ));
				}
				$variation_list[] = wc_attribute_label(str_replace('attribute_', '', $name)) . ': ' . rawurldecode($value);
			}
			$return .= implode('||', $variation_list);
		}
		return $return;
	}
	
	function filterHTML($data)
	{
		/* Filter known html key words, convert to printer appropriate commands */
		
		$encoding = get_option('star-cloudprnt-printer-encoding-select');
		
		$phpenc = "UTF-8";

		if($encoding == "1252")
			$phpenc="cp1252";

		$data = html_entity_decode($data, ENT_QUOTES, "UTF-8");

		if($phpenc !== "UFT-8")
			$data = mb_convert_encoding($data, $phpenc, "UTF-8");

		$data = str_replace(array("\r", "\n"), '', $data);				// Strip newlines

		return strip_tags($data);
	}

	function star_cloudprnt_create_receipt_items($order, &$printer, $max_chars)
	{
	
		$order_items = $order->get_items();
		foreach ($order_items as $item_id => $item_data)
		{

			$product_name = $item_data['name'];
			$product_id = $item_data['product_id'];
			$variation_id = $item_data['variation_id'];
			$product = wc_get_product($product_id);

			$altname = $product->get_attribute( 'star_cp_print_name' );				// Custom attribute can be used to override the product name on receipt

			$item_qty = wc_get_order_item_meta($item_id, "_qty", true);
			
			$item_total_price = floatval(wc_get_order_item_meta($item_id, "_line_total", true))
				+floatval(wc_get_order_item_meta($item_id, "_line_tax", true));
			
			$item_price = floatval($item_total_price) / intval($item_qty);
			$currencyHex = star_cloudprnt_get_codepage_currency_symbol();
			
			if ($variation_id != 0)
			{
				$product_variation = new WC_Product_Variation( $variation_id );
				$product_name = $product_variation->get_title();
			}

			if ($altname != "")
				$product_name = $altname;
			
			$formatted_item_price = number_format($item_price, 2, '.', '');
			$formatted_total_price = number_format($item_total_price, 2, '.', '');
			
			$printer->set_text_emphasized();
			$printer->add_text_line(filterHTML($product_name." - ID: ".$product_id.""));
			$printer->cancel_text_emphasized();
			
			$meta = $item_data->get_formatted_meta_data("_", TRUE);

			foreach ($meta as $meta_key => $meta_item)
			{
				// Use $meta_item->key for the raw (non display formatted) key name
				$printer->add_text_line(filterHTML(" ".$meta_item->display_key.": ".$meta_item->display_value));
			}
			
			$printer->add_text_line(star_cloudprnt_get_column_separated_data(array(" Qty: ".
						$item_qty." x Cost: ".$currencyHex.$formatted_item_price,
						$currencyHex.$formatted_total_price), $max_chars));
		}
	}
	
	function star_cloudprnt_create_receipt_order_meta_data($meta_data, &$printer, $max_chars)
	{
		if(get_option('star-cloudprnt-print-order-meta-cb') != "on")
			return;
		
		$is_printed = false;

		$print_hidden = get_option('star-cloudprnt-print-order-meta-hidden') == "on";
		
		foreach ($meta_data as $item_id => $meta_data_item)
		{
			$item_data = $meta_data_item->get_data();

			// Skip hidden fields (any field whose key begins with a "_", by convention)
			if(!$print_hidden && mb_substr($item_data["key"], 0, 1) == "_")
				continue;

			if(! $is_printed)
			{
				$is_printed = true;
				$printer->set_text_emphasized();
				$printer->add_text_line("Additional Order Information");
				$printer->cancel_text_emphasized();
			}

			$printer->add_text_line(filterHTML($item_data["key"]) . ": " . filterHTML($item_data["value"]));
		}
		
		if($is_printed)	$printer->add_text_line("");
	}


	function star_cloudprnt_create_address($order, $order_meta, &$printer)
	{
		$fname = $order_meta['_shipping_first_name'][0];
		$lname = $order_meta['_shipping_last_name'][0];
		$a1 = $order_meta['_shipping_address_1'][0];
		$a2 = $order_meta['_shipping_address_2'][0];
		$city = $order_meta['_shipping_city'][0];
		$state = $order_meta['_shipping_state'][0];
		$postcode = $order_meta['_shipping_postcode'][0];
		$tel = $order_meta['_billing_phone'][0];
		
		$printer->set_text_emphasized();
		if ($a1 == '')
		{
			$printer->add_text_line("Billing Address:");
			$printer->cancel_text_emphasized();
			$fname = $order_meta['_billing_first_name'][0];
			$lname = $order_meta['_billing_last_name'][0];
			$a1 = $order_meta['_billing_address_1'][0];
		
			$a2 = $order_meta['_billing_address_2'][0];
			$city = $order_meta['_billing_city'][0];
			$state = $order_meta['_billing_state'][0];
			$postcode = $order_meta['_billing_postcode'][0];
		}
		else
		{
			$printer->add_text_line("Shipping Address:");
			$printer->cancel_text_emphasized();
		}
		
		$printer->add_text_line($fname." ".$lname);
		$printer->add_text_line($a1);
		if ($a2 != '') $printer->add_text_line($a2);
		if ($city != '') $printer->add_text_line($city);
		if ($state != '') $printer->add_text_line($state);
		if ($postcode != '') $printer->add_text_line($postcode);
		$printer->add_text_line("Tel: ".$tel);
	}
	
	function star_cloudprnt_print_order_summary($selectedPrinter, $file, $order_id)
	{

		$order = wc_get_order($order_id);
		$order_number = $order->get_order_number();			// Displayed order number may be different to order_id when using some plugins
		$shipping_items = @array_shift($order->get_items('shipping'));
		$order_meta = get_post_meta($order_id);
		
		$meta_data = $order->get_meta_data();
		
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		
		if ($selectedPrinter['format'] == "txt") {
			$printer = new Star_CloudPRNT_Text_Plain_Job($selectedPrinter, $file);
		} else if ($selectedPrinter['format'] == "slt") {
			$printer = new Star_CloudPRNT_Star_Line_Mode_Job($selectedPrinter, $file);
		} else if ($selectedPrinter['format'] == "slm") {
			$printer = new Star_CloudPRNT_Star_Line_Mode_Job($selectedPrinter, $file);
		} else if ($selectedPrinter['format'] == "spt") {
			$printer = new Star_CloudPRNT_Star_Prnt_Job($selectedPrinter, $file);
			
		} else {
			$printer = new Star_CloudPRNT_Text_Plain_Job($selectedPrinter, $file);
		}
		
		$printer->set_codepage(get_option('star-cloudprnt-printer-encoding-select'));
		if (get_option('star-cloudprnt-print-logo-top-input')) $printer->add_nv_logo(esc_attr(get_option('star-cloudprnt-print-logo-top-input')));
		$printer->set_text_emphasized();
		$printer->set_text_center_align();
		$printer->set_font_magnification(2, 2);
		if($selectedPrinter['columns'] < 40) {
			$printer->add_text_line("ORDER");
			$printer->add_text_line("NOTIFICATION");
		} else {
			$printer->add_text_line("ORDER NOTIFICATION");
		}
		$printer->set_text_left_align();
		$printer->cancel_text_emphasized();
		$printer->set_font_magnification(1, 1);
		$printer->add_new_line(1);
		//$printer->add_text_line(star_cloudprnt_get_column_separated_data(array("Order #".$order_id, date("d-m-y H:i:s", time())), $selectedPrinter['columns']));
		//$printer->add_text_line(star_cloudprnt_get_column_separated_data(array("Order #".$order_number, date("d-m-y H:i:s", time())), $selectedPrinter['columns']));
		$printer->add_text_line(star_cloudprnt_get_column_separated_data(array("Order #".$order_number, date("{$date_format} {$time_format}", current_time('timestamp'))), $selectedPrinter['columns']));
		
		$printer->add_new_line(1);
		//$printer->add_text_line("Order Status: ".star_cloudprnt_parse_order_status($order->post->post_status));
		$printer->add_text_line("Order Status: ".$order->get_status());
		//$printer->add_text_line("Order Date: ".$order->order_date);
		//$printer->add_text_line("Order Date: ".$order->get_date_created());
		$order_date = date("{$date_format} {$time_format}", $order->get_date_created()->getOffsetTimestamp());
		$printer->add_text_line("Order Date: {$order_date}");	
		
		if (isset($shipping_items['name']))
		{
			$printer->add_new_line(1);
			$printer->add_text_line("Shipping Method: ".$shipping_items['name']);
		}
		$printer->add_text_line("Payment Method: ".$order_meta['_payment_method_title'][0]);
		$printer->add_new_line(1);
		$printer->add_text_line(star_cloudprnt_get_column_separated_data(array('ITEM', 'TOTAL'), $selectedPrinter['columns']));
		$printer->add_text_line(star_cloudprnt_get_seperator($selectedPrinter['columns']));
		
		star_cloudprnt_create_receipt_items($order, $printer, $selectedPrinter['columns']);

		$printer->add_new_line(1);
		$printer->set_text_right_align();
		$formatted_overall_total_price = number_format($order_meta['_order_total'][0], 2, '.', '');
		$printer->add_text_line("TOTAL     ".star_cloudprnt_get_codepage_currency_symbol().$formatted_overall_total_price);
		$printer->set_text_left_align();
		$printer->add_new_line(1);
		$printer->add_text_line("All prices are inclusive of tax (if applicable).");
		$printer->add_new_line(1);
		
		star_cloudprnt_create_receipt_order_meta_data($meta_data, $printer, $selectedPrinter['columns']);
		
		star_cloudprnt_create_address($order, $order_meta, $printer);
		
		$printer->add_new_line(1);
		$printer->set_text_emphasized();
		$printer->add_text_line("Customer Provided Notes:");
		$printer->cancel_text_emphasized();
		
		$notes = star_cloudprnt_get_wc_order_notes($order_id);
		$printer->add_text_line(empty($notes) ? "None" : $notes);
		
		
		if (get_option('star-cloudprnt-print-logo-bottom-input')) $printer->add_nv_logo(esc_attr(get_option('star-cloudprnt-print-logo-bottom-input')));
		
		$copies=intval(get_option("star-cloudprnt-print-copies-input"));
		if($copies < 1) $copies = 1;
		
		$printer->printjob($copies);
	}
	
	function star_cloudprnt_trigger_print($order_id)
	{

		$extension = STAR_CLOUDPRNT_SPOOL_FILE_FORMAT;	
		
		$selectedPrinterMac = "";
		$selectedPrinter = array();
		$printerList = star_cloudprnt_get_printer_list();
		if (!empty($printerList))
		{
		
			foreach ($printerList as $printer)
			{
				if (get_option('star-cloudprnt-printer-select') == $printer['name'])
				{
					$selectedPrinter = $printer;
					$selectedPrinterMac = $printer['printerMAC'];
					break;
				}
			}
			
			if (sizeof($selectedPrinter) == 0) {
				$selectedPrinter = $printerList[0];
			}
			
			/* Decide best printer emulation and print width as far as possible
			   NOTE: this is not the ideal way, but suits the existing
			   code structure. Will be reviewed.
			   */
			
			$encodings = $selectedPrinter['Encodings'];
			$columns = STAR_CLOUDPRNT_MAX_CHARACTERS_THREE_INCH;
			if (strpos($encodings, "application/vnd.star.line;") !== false) {
				/* There is no guarantee that printers will always return zero spacing between
				   the encoding name and separating semi-colon. But, definitely the HIX does, socket_accept
				   this is enough to ensure that thermal print mode is always used on HIX printers
				   with pre 1.5 firmware. This matches older plugin behaviour and therefore
				   avoids breaking customer sites.
				*/
				$extension = "slt";
			} else if (strpos($encodings, "application/vnd.star.linematrix") !== false) {
				$extension = "slm";
				$columns = STAR_CLOUDPRNT_MAX_CHARACTERS_DOT_THREE_INCH;
			} else if (strpos($encodings, "application/vnd.star.line") !== false) {
				// a second check for Line mode - just in case the above one didn't catch item
				// and after the "linemodematrix" check, to avoid a false match.
				$extension = "slt";
			} else if (strpos($encodings, 'application/vnd.star.starprnt') !== false) {
				$extension = "spt";
			} else if (strpos($encodings, "text/plain") !== false) {
				$extension = "txt";
			} 
			
			if ($selectedPrinter['ClientType'] == "Star mC-Print2") {
				$columns = STAR_CLOUDPRNT_MAX_CHARACTERS_TWO_INCH;
			}
			//var_dump($selectedPrinter);
			//print("Chosen Print Format:".$extension.", Columns:".$columns. "<br/>");
			
			$selectedPrinter['format'] = $extension;
			$selectedPrinter['columns'] = $columns;
			
			$file = STAR_CLOUDPRNT_PRINTER_PENDING_SAVE_PATH.star_cloudprnt_get_os_path("/order_".$order_id."_".time().".".$extension);

			if ($selectedPrinter !== "") star_cloudprnt_print_order_summary($selectedPrinter, $file, $order_id);
		}
	}
	
	function star_cloudprnt_order_reprint_action( $actions ) {
		global $theorder;

		$actions['star_cloudprnt_reprint_action'] = __( 'Print via Star CloudPRNT', 'my-textdomain' );
		return $actions;
	}

	function star_cloudprnt_reprint($order)
	{
		star_cloudprnt_trigger_print($order->get_id());
	}

	function star_cloudprnt_setup_order_handler()
	{
		if (selected(get_option('star-cloudprnt-select'), "enable", false) !== "")
		{
			add_action( 'woocommerce_order_actions', 'star_cloudprnt_order_reprint_action' );
			
			
			$trigger = get_option('star-cloudprnt-trigger');
			if($trigger === 'thankyou') {
				add_action('woocommerce_thankyou', 'star_cloudprnt_trigger_print', 1, 1);
			} elseif ($trigger === 'status_processing') {
				add_action('woocommerce_order_status_processing', 'star_cloudprnt_trigger_print', 1, 1);
			} elseif ($trigger === 'status_completed') {
				add_action('woocommerce_order_status_completed', 'star_cloudprnt_trigger_print', 1, 1);
			}

			add_action('woocommerce_order_action_star_cloudprnt_reprint_action', 'star_cloudprnt_reprint', 1, 1 );
		}
	}
?>