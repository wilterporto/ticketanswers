<?php

class PluginTicketanswersConfig extends CommonDBTM {
    
   static protected $notable = true;
   
   static function getMenuName() {
      return __('Ticket Answers', 'ticketanswers');
   }
   
   static function getMenuContent() {
      $menu = array();
      $menu['title'] = __('Ticket Answers', 'ticketanswers');
      $menu['page']  = '/plugins/ticketanswers/front/config.php';
      $menu['icon']  = 'fas fa-cog';
      
      return $menu;
   }
}
