<?php class ProductController extends DatabaseController {
public function affectDataToRow(&$row, $sub_rows){

    if(isset($sub_rows['account_user'])){
        $account_user = array_filter($sub_rows['account_user'], function($item) use ($row) { 
            return $item->account_user == $row->account_user;
        });
        $row->author = count($account_user) == 1 ? array_shift($account_user) : null;
    }

    if(isset($sub_rows['product'])){
        $product = array_filter($sub_rows['product'], function($item) use ($row) { 
            return $item->Id_product == $row->Id_product;
        });
        $row->product = count($product) == 1 ? array_shift($product) : null;
    }

    if(isset($sub_rows['image'])){
        $images = array_values(array_filter($sub_rows['image'], function($item) use ($row) { 
            return $item->Id_article == $row->Id_article;
        }));
        if(isset($images)){
            $row->images_list = $images;
        }
    }

    if(isset($sub_rows['sub_category'])){
        $comments = array_values(array_filter($sub_rows['sub_category'], function($item) use ($row) { 
            return $item->Id_article == $row->Id_article;
        }));
        if(isset($comments)){
            $row->comments_list = $comments;
        }
    }

    if(isset($sub_rows['category'])){
        $category = array_values(array_filter($sub_rows['category'], function($item) use ($row) { 
            return $item->Id_article == $row->Id_article;
        }));
        if(isset($category)){
            $row->category_list = array_column($category,'category');
        }
    }
}
}?>