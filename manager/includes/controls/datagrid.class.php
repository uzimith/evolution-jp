<?php
#
# DataGrid Class
# Created By Raymond Irving 15-Feb,2004
# Based on CLASP 2.0 (www.claspdev.com)
# -----------------------------------------
# Licensed under the LGPL
# -----------------------------------------
#

$__DataGridCnt=0;

class DataGrid {

	var $ds; // datasource

	var $pageSize;			// pager settings
	var $pageNumber;
	var $pager;
	var $pagerLocation;		// top-right, top-left, bottom-left, bottom-right, both-left, both-right

	var $cssStyle;
	var $cssClass;

	var $columnHeaderStyle;
	var $columnHeaderClass;
	var $itemStyle;
	var $itemClass;
	var $altItemStyle;
	var $altItemClass;
	
	var $fields;
	var $columns;
	var $colWidths;
	var $colAligns;
	var $colWraps;
	var $colColors;
	var $colTypes;			// coltype1, coltype2, etc or coltype1:format1, e.g. date:%Y %m
							// data type: integer,float,currency,date
	
	var $header;
	var $footer;
	var $cellPadding;
	var $cellSpacing;

	var $rowAlign;			// vertical alignment: top, middle, bottom
	var $rowIdField;
	
	var $noRecordMsg = "No records found.";

	function DataGrid($id,$ds,$pageSize=20,$pageNumber=-1) {
		global $__DataGridCnt;
		
		// set id
		$__DataGridCnt++;
		$this->id = $this->id ? $id:"dg".$__DataGridCnt;

		// set datasource
		$this->ds = $ds;
		
		// set pager
		$this->pageSize = $pageSize;
		$this->pageNumber = $pageNumber; // by setting pager to -1 will cause pager to load it's last page number
		$this->pagerLocation = 'top-right';
	}

	function setDataSource($ds){
		$this->ds = $ds;
	}
	
	function RenderRowFnc($n,$row){
		if ($this->_alt==0) {$Style = $this->_itemStyle;$Class = $this->_itemClass;$this->_alt=1;}
		else {$Style = $this->_altItemStyle;$Class = $this->_altItemClass; $this->_alt=0;}
		$o = "<tr>";
		for($c=0;$c<$this->_colcount;$c++){
			$colStyle = $Style;
			$fld=trim($this->_fieldnames[$c]);
			$width=$this->_colwidths[$c];
			$align=$this->_colaligns[$c];
			$color=$this->_colcolors[$c];
			$type=$this->_coltypes[$c];
			$nowrap=$this->_colwraps[$c];
			$value = $row[($this->_isDataset && $fld ? $fld:$c)];
			if($color && $Style) $colStyle = substr($colStyle,0,-1).";background-color:$color;'";
			$value = $this->formatColumnValue($row,$value,$type,$align);
			if($align)  $align  = 'align="'   . $align  . '"';
			if($color)  $color  = 'bgcolor="' . $color  . '"';
			if($nowrap) $nowrap = 'nowrap="'  . $nowrap . '"';
			if($width)  $width  = 'width="'   . $width  . '"';
			$attr = '';
			foreach(array($colStyle,$Class,$align,$color,$nowrap,$width) as $v)
			{
				$v = trim($v);
				if(!empty($v)) $attr .= ' ' . $v;
			}
			$o .= '<td' . $attr . '>' . $value . '</td>';
		}
		$o.="</tr>\n";
		return $o;
	}
	
	// format column values
	function formatColumnValue($row,$value,$type,&$align){
		global $modx;
		if(strpos($type,":")!==false) list($type,$type_format) = explode(":",$type,2);
		switch (strtolower($type)) {
			case "integer":
				if($align=="") $align="right";
				$value = number_format($value);
				break;

			case "float":
				if($align=="") $align="right";
				if(!$type_format) $type_format = 2;
				$value = number_format($value,$type_format);
				break;

			case "currency":
				if($align=="") $align="right";
				if(!$type_format) $type_format = 2;
				$value = "$".number_format($value,$type_format);
				break;
				
			case "date":
				if(!empty($value))
				{
				if($align=="") $align="right";
				if(!is_numeric($value)) $value = strtotime($value);
				if(!$type_format) $type_format = "%A %d, %B %Y";
				$value = $modx->mb_strftime($type_format,$value);
				}
				else
				{
					if($align=="") $align="center";
					$value = '-';
				}
				break;
			
			case "boolean":
				if ($align=='') $align="center";
				$value = number_format($value);
				if ($value) {
					$value = '&bull;';
				} else {
					$value = '&nbsp;';
				}
				break;

			case "template":
				// replace [+value+] first
				$value = str_replace("[+value+]",$value,$type_format);
				// replace other [+fields+]
				if(strpos($value,"[+")!==false) foreach($row as $k=>$v){
					$value = str_replace("[+$k+]",$v,$value);
				}
				break;
				
		}
		return $value;
	}

	function render()
	{
		global $modx;
		
		$columnHeaderStyle	= ($this->columnHeaderStyle)? 'style="' .$this->columnHeaderStyle. '"':'';
		$columnHeaderClass	= ($this->columnHeaderClass)? 'class="' .$this->columnHeaderClass. '"':'';
		$cssStyle			= ($this->cssStyle)? 'style="' .$this->cssStyle . '"':'';
		$cssClass			= ($this->cssClass)? 'class="' .$this->cssClass. '"':'';
		
		$pagerClass			= ($this->pagerClass)? 'class="'.$this->pagerClass.'"':'';
		$pagerStyle			= ($this->pagerStyle)? 'style="'.$this->pagerStyle.'"':'style="background-color:#ffffff;"';

		$this->_itemStyle	= ($this->itemStyle)?    'style="' . $this->itemStyle . '"':'';
		$this->_itemClass	= ($this->itemClass)?    'class="' . $this->itemClass . '"':'';
		$this->_altItemStyle= ($this->altItemStyle)? 'style="' .$this->altItemStyle . '"':'';
		$this->_altItemClass= ($this->altItemClass)? 'class="' .$this->altItemClass . '"':'';

		$this->_alt = 0;
		$this->_total = 0;
		
		$this->_isDataset = is_resource($this->ds); // if not dataset then treat as array
		if( is_object($this->ds) ){  // for PDO
			$this->_isDataset = true;
			$this->_isPDO = true;
		}

		if(!$cssStyle && !$cssClass) $cssStyle = 'style="width:100%;font-family:verdana,arial; font-size:12px;"';
		if(!$this->_itemStyle && !$this->_itemClass) $this->_itemStyle = "style='color:#333333;'";
		if(!$this->_altItemStyle && !$this->_altItemClass) $this->_altItemStyle = "style='color:#333333;background-color:#eeeeee'";

		if($this->_isDataset && !$this->columns) {
			if( $this->_isPDO ) {
				$cols = $this->ds->columnCount();
				for($i=0;$i<$cols;$i++){
					$md=$ds->getColumnMeta($i);
					$this->columns.= ($i ? ",":"").$md['name'];
				}
			}else{
				$cols = mysql_num_fields($this->ds);
				for($i=0;$i<$cols;$i++)
					$this->columns.= ($i ? ",":"").mysql_field_name($this->ds,$i);
			}
		}
		
		// start grid
		$cellpadding = '';
		$cellspacing = '';
		if(isset($this->cellPadding)) $cellpadding = 'cellpadding="' . (int)$this->cellPadding . '"';
		if(isset($this->cellSpacing)) $cellspacing = 'cellspacing="' . (int)$this->cellSpacing . '"';
		$attr = '';
		foreach(array($cssClass,$cssStyle,$cellpadding,$cellspacing) as $v)
		{
			$v = trim($v);
			if(!empty($v)) $attr .= ' ' . $v;
		}
		$tblStart	= '<table' . $attr . '>' . PHP_EOL;
		$tblEnd		= '</table>' . PHP_EOL;
		
		// build column header
		$this->_colnames  = explode((strstr($this->columns,"||")  !==false ? "||":","),$this->columns);
		$this->_colwidths = explode((strstr($this->colWidths,"||")!==false ? "||":","),$this->colWidths);
		$this->_colaligns = explode((strstr($this->colAligns,"||")!==false ? "||":","),$this->colAligns);
		$this->_colwraps  = explode((strstr($this->colWraps,"||") !==false ? "||":","),$this->colWraps);
		$this->_colcolors = explode((strstr($this->colColors,"||")!==false ? "||":","),$this->colColors);
		$this->_coltypes  = explode((strstr($this->colTypes,"||") !==false ? "||":","),$this->colTypes);
		$this->_colcount  = count($this->_colnames);
		if(!$this->_isDataset) {
			$this->ds = preg_split((strstr($this->ds,"||")!==false ? "/\|\|/":"/[,\t\n]/"),$this->ds);
			$this->ds = array_chunk($this->ds, $this->_colcount);
		}
		$tblColHdr ='<thead>' . PHP_EOL . '<tr>';
		for($c=0;$c<$this->_colcount;$c++){
			$name=$this->_colnames[$c];
			$width=$this->_colwidths[$c];
			if(!empty($width)) $width = 'width="' . $width . '"';
			$attr = '';
			foreach(array($columnHeaderStyle,$columnHeaderClass,$width) as $v)
			{
				$v = trim($v);
				if(!empty($v)) $attr .= ' ' . $v;
			}
			$tblColHdr .= '<th' . $attr . '>' . $name . '</th>';
		}
		$tblColHdr.="</tr></thead>\n";

		// build rows
		$rowcount = $this->_isDataset ? $modx->db->getRecordCount($this->ds):count($this->ds);
		$this->_fieldnames = explode(",",$this->fields);
		if($rowcount==0) $tblRows.= "<tr><td ".$this->_itemStyle." ".$this->_itemClass." colspan='".$this->_colcount."'>".$this->noRecordMsg."</td></tr>\n";
		else {
			// render grid items
			if($this->pageSize<=0) {
				for($r=0;$r<$rowcount;$r++){
					$row = $this->_isDataset ? $modx->db->getRow($this->ds):$this->ds[$r];
					$tblRows.= $this->RenderRowFnc($r+1,$row);
				}
			}
			else {
				if(!$this->pager) {
					include_once dirname(__FILE__)."/datasetpager.class.php";
					$this->pager = new DataSetPager($this->id,$this->ds,$this->pageSize,$this->pageNumber);
					$this->pager->setRenderRowFnc($this); // pass this object
					$this->pager->cssStyle = $pagerStyle;
					$this->pager->cssClass = $pagerClass;
				}
				else {
					$this->pager->pageSize	= $this->pageSize;
					$this->pager->pageNumber= $this->pageNumber;
				}

				$this->pager->render();
				$tblRows = $this->pager->getRenderedRows();
				$tblPager = $this->pager->getRenderedPager();
			}
		}
		
		// setup header,pager and footer
		$o = $tblStart;
		$ptop = (substr($this->pagerLocation,0,3)=="top")||(substr($this->pagerLocation,0,4)=="both");
		$pbot = (substr($this->pagerLocation,0,3)=="bot")||(substr($this->pagerLocation,0,4)=="both");
		if($this->header) $o.="<tr><td colspan='".$this->_colcount."'>".$this->header."</td></tr>";
		if($tblPager && $ptop) $o.="<tr><td align='".(substr($this->pagerLocation,-4)=="left"? "left":"right")."' $pagerClass $pagerStyle colspan='".$this->_colcount."'>".$tblPager."&nbsp;</td></tr>";
		$o.=$tblColHdr.$tblRows;
		if($tblPager && $pbot) $o.="<tr><td align='".(substr($this->pagerLocation,-4)=="left"? "left":"right")."' $pagerClass $pagerStyle colspan='".$this->_colcount."'>".$tblPager."&nbsp;</td></tr>";
		if($this->footer) $o.="<tr><td colspan='".$this->_colcount."'>".$this->footer."</td></tr>";
		$o.= $tblEnd;
		return $o;
	}
}
