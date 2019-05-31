<?php
namespace app\common\model;
use think\Model;

class AdPosition extends Model{

	public function Ad()
	{
		return $this->hasMany('ad','pid','position_id');
	}


}
