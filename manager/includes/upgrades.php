<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

global $default_config;
$default_config = include_once($modx->config['base_path'] . 'manager/includes/default.config.php');

run_update($settings_version);
if($action==17) $modx->clearCache();

disableOldCarbonTheme();

function run_update($pre_version)
{
	global $modx;
	
	$pre_version = strtolower($pre_version);
	$pre_version = str_replace(array('j','rc','-r'), array('','RC','-'), $pre_version);
		
	if(version_compare($pre_version,'1.0.5') < 0) {
		update_tbl_system_settings();
		$msg = 'Update before 1.0.5';
		$modx->logEvent(0,1,$msg,$msg);
	}
	
	if(version_compare($pre_version,'1.0.6') < 0) {
		update_config_custom_contenttype();
		update_config_default_template_method();
		$msg = 'Update before 1.0.6';
		$modx->logEvent(0,1,$msg,$msg);
	}
	
	if(version_compare($pre_version,'1.0.7') < 0) {
		disableLegacyPlugins();
		$msg = 'Update before 1.0.7';
		$modx->logEvent(0,1,$msg,$msg);
	}
	
	if(0 < version_compare($pre_version,'1.0.4') && version_compare($pre_version,'1.0.7') < 0) {
		delete_actionphp();
		$msg = 'Delete action.php is success';
		$modx->logEvent(0,1,$msg,$msg);
	}
	
	if(0 <= version_compare($pre_version,'1.0.8')) {
		updateMenus();
		$msg = 'Update menu';
		$modx->logEvent(0,1,$msg,$msg);
	}
	
	update_tbl_user_roles();
	disableOldFckEditor();
}

function updateMenus()
{
	global $modx;
	
	if($modx->config['topmenu_site'] === 'home,preview,refresh_site,search,add_resource,add_weblink')
		$modx->regOption('topmenu_site', 'home,preview,refresh_site,resource_list,add_resource,add_weblink');
	if($modx->config['topmenu_tools'] === 'bk_manager,import_site,export_site,edit_settings')
		$modx->regOption('topmenu_tools','bk_manager,import_site,export_site,search,edit_settings');
}

function disableOldCarbonTheme() {
	global $modx, $default_config;

	$old_manager_theme = $modx->config['manager_theme'];
	
	$old_manager_dir= MODX_BASE_PATH . "manager/media/style/{$old_manager_theme}/";
	
	if(
		 is_dir("{$old_manager_dir}manager")
	||  is_file("{$old_manager_dir}sysalert_style.php")
	|| !is_file("{$old_manager_dir}style.php")
	|| ($old_manager_theme==='MODxCarbon' && !is_dir(MODX_MANAGER_PATH . 'media/style/MODxCarbon/images/icons/32x'))
	)
	{
		$modx->regOption('manager_theme',$default_config['manager_theme']);
	}
}

function disableOldFckEditor()
{
	global $modx, $default_config;
	
	$tpl_path = MODX_BASE_PATH . 'assets/plugins/fckeditor/plugin.fckeditor.tpl';
	if(!is_file($tpl_path)) return;
	$file = file_get_contents($tpl_path);
	if(strpos($file,'FCKeditor v2.1.1')===false) return;
	$modx->regOption('which_editor',$default_config['which_editor']);
}
function disableLegacyPlugins()
{
	global $modx;
	
	$modx->db->update("`disabled`='1'",'[+prefix+]site_plugins',"`name`='Bindings機能の有効無効'"); // jp only
	$modx->db->update("`disabled`='1'",'[+prefix+]site_plugins',"`name`='Bottom Button Bar'");
}

function update_config_custom_contenttype()
{
	global $modx,$custom_contenttype;
	
	$search[] = '';
	$search[] = 'text/css,text/html,text/javascript,text/plain,text/xml';
	$search[] = 'application/rss+xml,application/pdf,application/msword,application/excel,text/html,text/css,text/xml,text/javascript,text/plain';
	$replace  = 'application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain';
	
	foreach($search as $v)
	{
		if($v === $modx->config['custom_contenttype']) $modx->regOption('custom_contenttype', $replace);
	}
}

function update_config_default_template_method()
{
	global $modx,$auto_template_logic;
	
	$rs = $modx->db->select('properties,disabled', '[+prefix+]site_plugins', "`name`='Inherit Parent Template'");
	$row = $modx->db->getRow($rs);
	if($row)
	{
		$modx->db->update("`disabled`='1'", '[+prefix+]site_plugins', "`name` IN ('Inherit Parent Template')");
	}
	if(!$row || !isset($modx->config['auto_template_logic'])) $auto_template_logic = 'sibling'; // not installed
	else
	{
		if($row['disabled'] == 1) $auto_template_logic = 'sibling'; // installed but disabled
		else
		{
			// installed, enabled .. see how it's configured
			$properties = $modx->parseProperties($row['properties']);
			if(isset($properties['inheritTemplate']))
			{
				if($properties['inheritTemplate'] == 'From First Sibling')
				{
					$auto_template_logic = 'sibling';
				}
			}
		}
	}
}

function update_tbl_user_roles()
{
	global $modx;
	
	$f['view_unpublished'] = '1';
	$f['publish_document'] = '1';
	$f['edit_chunk']       = '1';
	$f['new_chunk']        = '1';
	$f['save_chunk']       = '1';
	$f['delete_chunk']     = '1';
	$f['import_static']    = '1';
	$f['export_static']    = '1';
	$f['empty_trash']      = '1';
	$f['remove_locks']     = '1';
	$f['view_schedule']    = '1';
	$modx->db->update($f, '[+prefix+]user_roles', "`id`='1'");
}

function update_tbl_system_settings()
{
	global $modx,$use_udperms;
	if($modx->config['validate_referer']==='00')         $modx->regOption('validate_referer','0');
	if($modx->config['upload_maxsize']==='1048576')      $modx->regOption('upload_maxsize','');
	if($modx->config['emailsender']==='you@example.com') $modx->regOption('emailsender',$_SESSION['mgrEmail']);
	
	$rs = $modx->db->select('*','[+prefix+]document_groups');
	$use_udperms = ($modx->db->getRecordCount($rs)==0) ? '0' : '1';
	$modx->config['use_udperms'] = $modx->regOption('use_udperms',$use_udperms);
}

function delete_actionphp()
{
	global $modx;
	
	$path = $modx->config['base_path'] . 'action.php';
	if(is_file($path))
	{
		$src = file_get_contents($path);
		if(strpos($src,'if(strpos($path,MODX_MANAGER_PATH)!==0)')===false)
		{
			@unlink($modx->config['base_path'] . 'action.php');
		}
	}
}
