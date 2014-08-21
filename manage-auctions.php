<?php
if( ! class_exists( 'WP_List_Table' ) ) {
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
$this->auction_type = (isset($_GET["auction_type"]) && $_GET["auction_type"]=="expired")?"expired":"live";

class Auctions_List_Table extends WP_List_Table {        
    var $allData;
    var $auction_type;
         
    function wdm_get_data(){
        if(isset($_GET["auction_type"]) && $_GET["auction_type"]=="expired")
        {
            $args = array(
                'posts_per_page'  => -1,
                'post_type'       => 'ultimate-auction',
                'auction-status'  => 'expired',
		'orderby' => 'meta_value',
		'meta_key' => 'wdm_listing_ends',
		'order' => 'DESC'
                );
        }
        else
        {
            $args = array(
                'posts_per_page'  => -1,
                'post_type'       => 'ultimate-auction',
                'auction-status'  => 'live' 
                );
        }
        
        $auction_item_array = get_posts( $args );
        $data_array=array();
        
        foreach($auction_item_array as $single_auction){
            
            $act_term = wp_get_post_terms($single_auction->ID, 'auction-status',array("fields" => "names"));
            if(mktime() >= strtotime(get_post_meta($single_auction->ID,'wdm_listing_ends',true))){
				if(!in_array('expired',$act_term))
				{
					$check_tm = term_exists('expired', 'auction-status');
					wp_set_post_terms($single_auction->ID, $check_tm["term_id"], 'auction-status');
				}
            }
            
            $row = array();
            $row['ID']=$single_auction->ID;
            $row['title']=prepare_single_auction_title($single_auction->ID, $single_auction->post_title);
            $end_date = get_post_meta($single_auction->ID,'wdm_listing_ends', true);
            $row['date_created']= "<strong> ".__('Creation Date', 'wdm-ultimate-auction').":</strong> <br />".get_post_meta($single_auction->ID, 'wdm_creation_time', true)." <br /><br /> <strong>  ".__('Ending Date', 'wdm-ultimate-auction').":</strong> <br />".$end_date;
	    $thumb_img = get_post_meta($single_auction->ID,'wdm_auction_thumb', true);
	    if(empty($thumb_img) || $thumb_img == null)
	    {
		$thumb_img = plugins_url('img/no-pic.jpg', __FILE__);
	    }
            $row['image_1']="<input class='wdm_chk_auc_act' value=".$single_auction->ID." type='checkbox' style='margin: 0 5px 0 0;' />"."<img src='".$thumb_img."' width='90'";
            
            if($this->auction_type=="live")
            {
                $row['action']="<a href='?page=add-new-auction&edit_auction=".$single_auction->ID."'>".__('Edit', 'wdm-ultimate-auction')."</a> <br /><br /> <div id='wdm-delete-auction-".$single_auction->ID."' style='color:red;cursor:pointer;'>".__('Delete', 'wdm-ultimate-auction')." <span class='auc-ajax-img'></span></div> <br /> <div id='wdm-end-auction-".$single_auction->ID."' style='color:#21759B;cursor:pointer;'>".__('End Auction', 'wdm-ultimate-auction')."</div>";
                require('ajax-actions/end-auction.php');
            }
            else
            $row['action']="<div id='wdm-delete-auction-".$single_auction->ID."' style='color:red;cursor:pointer;'>".__('Delete', 'wdm-ultimate-auction')." <span class='auc-ajax-img'></span></div><br /><a href='?page=add-new-auction&edit_auction=".$single_auction->ID."&reactivate'>".__('Reactivate', 'wdm-ultimate-auction')."</a>";
            
            //for bidding logic
            $row['bidders'] = "";
            $row_bidders = "";
            global $wpdb;
            $currency_code = substr(get_option('wdm_currency'), -3);
            $query = "SELECT * FROM ".$wpdb->prefix."wdm_bidders WHERE auction_id =".$single_auction->ID." ORDER BY id DESC LIMIT 5";
            $results = $wpdb->get_results($query);
            
            if(!empty($results)){
                $cnt_bidder = 0;
                foreach($results as $result){
                    $row_bidders.="<li><strong><a href='#'>".$result->name."</a></strong> - ".$currency_code." ".$result->bid."</li>";
                    if($cnt_bidder == 0 )
                    {
                        $bidder_id = $result->id;
                        $bidder_name = $result->name;
                    }
                    
                    $cnt_bidder++;
                }
                $row["bidders"] = "<div class='wdm-bidder-list-".$single_auction->ID."'><ul>".$row_bidders."</ul></div>";
                $row["bidders"] .="<div id='wdm-cancel-bidder-".$bidder_id."' style='font-weight:bold;color:#21759B;cursor:pointer;'>".__('Cancel Last Bid', 'wdm-ultimate-auction')."</div>";
                $qry = "SELECT * FROM ".$wpdb->prefix."wdm_bidders WHERE auction_id =".$single_auction->ID." ORDER BY id DESC";
                $all_bids = $wpdb->get_results($qry);
                if(count($all_bids) > 5)
                $row["bidders"] .="<br />
                <a href='#' class='see-more showing-top-5' rel='".$single_auction->ID."' >".__('See more', 'wdm-ultimate-auction')."</a>";
                require('ajax-actions/cancel-bidder.php');
            }
            else{
                $row["bidders"] = __('No bids placed', 'wdm-ultimate-auction');
            }
          
            $start_price = get_post_meta($single_auction->ID,'wdm_opening_bid', true);
            $buy_it_now_price = get_post_meta($single_auction->ID,'wdm_buy_it_now',true);
	    
	    $row['current_price']  = "";
	    $row['final_price']  = "";
	    if(empty($start_price) && !empty($buy_it_now_price))
	    {
		$row['current_price']  = "<strong>".__('Buy Now Price', 'wdm-ultimate-auction').":</strong> <br />".$currency_code." ".$buy_it_now_price;
		$row['final_price']  = "<strong>".__('Buy Now Price', 'wdm-ultimate-auction').":</strong> <br />".$currency_code." ".$buy_it_now_price;
	    }
	    elseif(!empty($start_price))
	    {
		$query="SELECT MAX(bid) FROM ".$wpdb->prefix."wdm_bidders WHERE auction_id =".$single_auction->ID." ORDER BY id DESC";
		$curr_price = $wpdb->get_var($query);
		
		if(empty($curr_price))
			$curr_price = $start_price;
            
		$row['current_price']  = "<strong>".__('Starting Price', 'wdm-ultimate-auction').":</strong> <br />".$currency_code." ".$start_price;
		$row['current_price'] .= "<br /><br /> <strong>".__('Current Price', 'wdm-ultimate-auction').":</strong><br /> ".$currency_code." ".$curr_price;
		
		$row['final_price']  = "<strong>".__('Starting Price', 'wdm-ultimate-auction').":</strong> <br />".$currency_code." ".$start_price;
		$row['final_price'] .= "<br /><br /> <strong>".__('Final Price', 'wdm-ultimate-auction').":</strong><br /> ".$currency_code." ".$curr_price;
	    }
	    
            if($this->auction_type === "expired")
            {
                $row['email_payment'] = "";
                
                if(get_post_meta($single_auction->ID,'auction_bought_status',true) === 'bought')
                {
                    $row['email_payment'] = "<span class='wdm-auction-bought'>".__('Auction has been bought by paying Buy Now price', 'wdm-ultimate-auction')." <br/> [".$currency_code." ".$buy_it_now_price."] </span>";
                }
                
                else
                {
                    if(!empty($results))
                    {
			$reserve_price_met = get_post_meta($single_auction->ID, 'wdm_lowest_bid',true);
		    
			$bid_qry = "SELECT MAX(bid) FROM ".$wpdb->prefix."wdm_bidders WHERE auction_id =".$single_auction->ID." ORDER BY id DESC";
			$winner_bid = $wpdb->get_var($bid_qry);
			
			if($winner_bid >= $reserve_price_met)
			{
			    $email_qry = "SELECT email FROM ".$wpdb->prefix."wdm_bidders WHERE bid =".$winner_bid." AND auction_id =".$single_auction->ID." ORDER BY id DESC";
			    $winner_email = $wpdb->get_var($email_qry);
			    
			    $email_sent = get_post_meta($single_auction->ID,'auction_email_sent',true);
			    
			    $row['email_payment']  = "<strong>Status: </strong>";
			    if($email_sent === 'sent')
				$row['email_payment'] .= "<span style='color:green'>".__('Yes', 'wdm-ultimate-auction')."</span>";
			    else
				$row['email_payment'] .= "<span style='color:red'>".__('No', 'wdm-ultimate-auction')."</span>";
                            
				$row['email_payment'] .= "<br/><br/> <a href='' id='auction-resend-".$single_auction->ID."'>".__('Resend', 'wdm-ultimate-auction')."</a>";
                            
			    require('ajax-actions/resend-email.php');
			}
			else
			{
			    $row['email_payment'] = "<span style='color:#D64B00'>".__('Auction has expired without reaching its reserve price', 'wdm-ultimate-auction')."</span>";
			}
                    }
                }
            }
            
            $data_array[]=$row;
            
            require('ajax-actions/delete-auction.php');
        }
	
	require_once('ajax-actions/multi-delete.php');
	
        $this->allData=$data_array;
        return $data_array;            
    }               
               
    function get_columns(){
    if($this->auction_type=="live")
    $columns =   array(
    'ID'        => __('ID', 'wdm-ultimate-auction'),
    'image_1'   => '<input class="wdm_select_all_chk" type="checkbox" style="margin: 0 5px 0 0;" />'.__('Image', 'wdm-ultimate-auction'),
    'title' => __('Title', 'wdm-ultimate-auction'),
    'date_created' => __('Creation / Ending Date', 'wdm-ultimate-auction'),
    'current_price' => __('Starting / Current Price', 'wdm-ultimate-auction'),
    'bidders'   => __('Bids Placed', 'wdm-ultimate-auction'),
    'action'    => __('Actions', 'wdm-ultimate-auction')
    );
    else
    $columns =   array(
    'ID'        => __('ID', 'wdm-ultimate-auction'),
    'image_1'   => '<input class="wdm_select_all_chk" type="checkbox" style="margin: 0 5px 0 0;" />'.__('Image', 'wdm-ultimate-auction'),
    'title' => __('Title', 'wdm-ultimate-auction'),
    'date_created' => __('Creation / Ending Date', 'wdm-ultimate-auction'),
    'final_price' => __('Starting / Final Price', 'wdm-ultimate-auction'),
    'bidders'   => __('Bids Placed', 'wdm-ultimate-auction'),
    'email_payment'   => __('Email For Payment', 'wdm-ultimate-auction'),
    'action'    => __('Actions', 'wdm-ultimate-auction')
    );
    return $columns;  
    }
    
    function get_sortable_columns(){
        $sortable_columns = array(
                        'ID' => array('ID',false),
                        'title' => array('title',false)
                        //'date_created' => array('date_created',false)
                        );
        return $sortable_columns;
    }
    
    function prepare_items() {
    $this->auction_type = (isset($_GET["auction_type"]) && $_GET["auction_type"]=="expired")?"expired":"live";
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);
    $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'ID';
    if($orderby === 'title')
    {
	$this->items = $this->wdm_sort_array($this->wdm_get_data());
    }
    else
    {
	$this->items = $this->wdm_get_data();
    }
    }
    function get_result_e(){
        return $this->allData;    
    }
      
    function wdm_sort_array($args){
        if(!empty($args))
        {
        $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'ID';
	
	if($orderby === 'title')
	    $order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
	else
	    $order = 'desc';
	
        foreach ($args as $array) {
            $sort_key[] = $array[$orderby];
        }
        if($order=='asc')
            array_multisort($sort_key,SORT_ASC,$args);
        else
            array_multisort($sort_key,SORT_DESC,$args);
        } 
        return $args;
    }
    
    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'ID':
            case 'image_1':
            case 'title':
            case 'date_created':
            case 'action':
            case 'bidders':
            case 'current_price':
            case 'final_price':
            case 'email_payment':    
            return $item[ $column_name ];
            default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

}

if( isset( $_GET[ 'auction_type' ] ) ) {  
    $manage_auction_tab = $_GET[ 'auction_type' ];  
} 
else
$manage_auction_tab = 'live';  
?>
<ul class="subsubsub">
    <li><a href="?page=manage_auctions&auction_type=live" class="<?php echo $manage_auction_tab == 'live' ? 'current' : ''; ?>"><?php _e('Live Auctions', 'wdm-ultimate-auction');?></a>|</li>
    <li><a href="?page=manage_auctions&auction_type=expired" class="<?php echo $manage_auction_tab == 'expired' ? 'current' : ''; ?>"><?php _e('Expired Auctions', 'wdm-ultimate-auction');?></a></li>
</ul>
<br class="clear"><br class="clear">
<div style="float:left;">
    <select id="wdmua_del_all" style="float:left;margin-right: 10px;"><option value="del_all_wdm"><?php _e("Delete", "wdm-ultimate-auction");?></option></select>
    <input type="button" id="wdm_mult_chk_del" class="wdm_ua_act_links button-secondary" value="<?php _e("Apply", "wdm-ultimate-auction");?>" />
    <span class="wdmua_del_stats"></span>
</div>
<?php
$myListTable = new Auctions_List_Table();
$myListTable->prepare_items();
$myListTable->display();
?>