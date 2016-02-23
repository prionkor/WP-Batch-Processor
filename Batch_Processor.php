<?php

namespace Prionkor;

class Batch_Processor{

    public $page,
        $limit = 100,
        $prefix,
        $rotate = true,
        $transient = '_batch_process_paged',
        $item_type = 'post',
        $end_of_iteration = false;

    public function __construct($args, $prefix, $validate_args = array()){

        $t_length = strlen($this->transient);
        $p_length = strlen($prefix);

        if($t_length + $p_length > 45){
            $max = 45 - $t_length;
            throw new \Exception("Max length of prefix is $max. Your given prifix is $p_length chars long");
        }

        $this->page = (isset($args['paged'])) ? (int) $args['paged'] : $this->page;
        $this->limit = (isset($args['posts_per_page'])) ? (int) $args['posts_per_page'] : $this->limit;
        $this->limit = (isset($args['limit'])) ? (int) $args['limit'] : $this->limit;
        $this->transient = $prefix . $this->transient;
        $this->args = $args;

        if(empty($this->validate_args))
            $this->validate_args = $this->args;
    }

    public function get_items(){
        $limit = $this->limit;
        $transient = $this->transient;

        if( false === $page = get_transient($transient) ){
            $page = $this->page;
        }

        $page++;
        $it = $items = $this->_get_items($this->args, $page, $limit);

        $last_id = array_pop($it);

        if($last_id && !is_int($last_id)){
            if(isset($last_id->ID))
                $last_id = $last_id->ID; // it is a post object
            else if(isset($last_id->id)){
                $last_id = $last_id->id;
            }
        }

        $page = $this->validate_page($page, $last_id);

        set_transient($transient, $page, 15 * 60);

        return $items;

    }

    protected function _get_items($args, $page, $limit){

        $args['paged'] = $page;
        $args['posts_per_page'] = $limit;
        $args['orderby'] = 'ID';
        $args['order'] = 'ASC';

        if(!isset($args['fields']))
            $args['fields'] = 'ids';

        $query = new \WP_Query( $args ); // this is your query :)

        return $query->posts;

    }

    protected function validate_page($page, $last_id){

        $transient = $this->prefix . '_latest_id';

        if(!$last_id){
            $this->end_of_iteration = true;
            return 0;
        }

        if( false === $id = get_transient($transient) ){

            $args = $this->validate_args;
            $args['posts_per_page'] = 1;
            $args['fields'] = 'ids';

            unset($args['orderby']);
            unset($args['order']);

            $latest_post_id = get_posts($args);

            if(empty($latest_post_id)){
                $this->end_of_iteration = true;
                return 0;
            }

            $id = $latest_post_id[0];
            set_transient( $transient, $id, 60 * 5);
        }

        if($last_id < $id){
            return $page;
        }

        $this->end_of_iteration = true;
        return 0;

    }

}



