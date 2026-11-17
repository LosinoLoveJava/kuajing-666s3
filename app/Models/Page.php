<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App;

class Page extends Model
{
  public function getTranslation($field = '', $lang = false){
      $lang = $lang == false ? App::getLocale() : $lang;
      $page_translation = $this->hasMany(PageTranslation::class)->where('lang', $lang)->first();
      $value = $page_translation != null && $page_translation->$field !== null && $page_translation->$field !== '' ? $page_translation->$field : $this->$field;
      return $field === 'content' ? \App\Support\TurkishCopy::html($value, $lang) : \App\Support\TurkishCopy::text($value, $lang);
  }

  public function page_translations(){
    return $this->hasMany(PageTranslation::class);
  }
}
