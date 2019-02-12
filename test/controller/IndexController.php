<?php
use Oblind\Http\Controller;

class IndexController extends Controller {
  function indexAction() {
    $this->view('index.htm');
  }
}
