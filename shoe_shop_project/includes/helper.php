<?php
function createSlug($string) {
    $string = strtolower($string);
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}