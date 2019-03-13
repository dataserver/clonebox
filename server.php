<?php
// http://patorjk.com/software/taag/#p=display&c=c&f=Colossal&t=text

// Path to the front controller (this file)
define('APP_PATH', __DIR__ . DIRECTORY_SEPARATOR);

include_once(APP_PATH . 'config.php');
include_once(APP_PATH . 'lib/SimpleImage.php');
include_once(APP_PATH . 'lib/Urlify.php');
include_once(APP_PATH . 'lib/MyPDO.php');
include_once(APP_PATH . 'lib/File.inc.php');
include_once(APP_PATH . 'lib/Folder.inc.php');
include_once(APP_PATH . 'lib/Request.inc.php');
include_once(APP_PATH . 'lib/misc.func.php');

chdir(__DIR__);
$request = new Request;

$folder_id = (int) $request->getPostGet("folder", FILTER_SANITIZE_NUMBER_INT);
$file_id = (int) $request->getPostGet("file", FILTER_SANITIZE_NUMBER_INT);
$action = $request->getPostGet("action", FILTER_SANITIZE_STRING);
$action = !empty($action) ? $action : "get-all";


define('APP_METHOD', $action);
define('CURR_FOLDER_ID', $folder_id);
define('CURR_FILE_ID', $file_id);

switch ($action) {
	case 'file-upload':
		$errors = [];
		$data = [];
		$items = [];

		if ( isset($_FILES) && !empty($_FILES) ) {
			$file_array = $request->getFiles('upload_files');
			$parent_id = $request->getPost("parent_id", FILTER_SANITIZE_NUMBER_INT);
			$children_folder_path = $request->getPost("children_folder_path", FILTER_SANITIZE_STRING);
			// $children_folder_name = $request->getPost("children_folder_name", FILTER_SANITIZE_STRING);

			$parent_folder = new Folder;
			$parent_id = $parent_folder->get($parent_id)->id();
			if ( is_null($parent_id) ) {
				oops(400, 'parent folder is not defined.');
			}

			if ( $children_folder_path != "" &&  $children_folder_path != "/") {
				$folder = new Folder;
				$id = $folder->getIdToFolderPath($children_folder_path, $parent_id);
				$folder_id = ( is_null($id) ) ? 1 : $id;
			} else {
				$folder_id = $parent_id;
			}
			
			for ($i = 0; $i < count($file_array); $i++) {
				
				$saved_basename  = date('Ymd-His') .'-'. generateRandomString(5);
				$extension       = getFileExt( $file_array[$i]['name'] );
				$saved_as        = $saved_basename .'.'. $extension;
				if (in_array($extension, CONFIG['upload_denied_extensions'])) {
					$data['errors'][] = [
						'file' => $file->name,
						'message' => "File extension denied : " . $file->name,
					];
					continue;
				}
				if ( in_array($extension, ['gif','jpg','jpeg','png','webp']) ) {
					$thumbnail = CONFIG['thumbnail_prefix'] . $saved_basename .'.png';
				} else {
					$thumbnail = null;
				}
				
				$file = new File;
				$file->folder_id = $folder_id;
				$name = $file->sanitizeFilename($file_array[$i]['name']);
				$file->name = basename($name);
				$file->extension = getFileExt($file_array[$i]['name']);
				$file->size =  filesize($file_array[$i]['tmp_name']);
				$file->type = 'file';
				$file->thumbnail = $thumbnail;
				$file->saved_as = $saved_as;
				$file->add();

				if ( $file->id > 0 ) { // include in DB
					$upload_pointer = CONFIG['base_path_store'] . $file->saved_as;

					if ( move_uploaded_file($file_array[$i]['tmp_name'], $upload_pointer) ) {
						@chmod($upload_pointer, 0777 & ~umask());

						if ( in_array($file->extension, ['gif','jpg','jpeg','png','webp']) ){
							try {
								$image = new \claviska\SimpleImage($upload_pointer);
								$image->thumbnail(350, 350)->toFile( CONFIG['base_path_thumb'] . $file->thumbnail, 'image/png');
							} catch(Exception $err) {
								$errors[] =  $err->getMessage();
								$file->thumbnail = $error;
							}
						}
						$items[] = [
							'file' => $file->name,
							'message' => "Uploaded " . $file->name,
							'thumbnail' => $file->thumbnail,
						];
					}
				} else {
					$errors[] = [
						'message' => "file '". $file->name . "' not included into database.",
					];
				}
			}
		} else {
			$errors[] = [
				'message' => "No file has being sent",
			];
		}
		if ( count($errors) > 0 && count($items) == 0 ) { // only errors
			$json = [
				'method' => 'upload',
				'params' => [
					'folder' => ''
				],
				// 'debug' => var_export($_FILES),
				'code' => 400,
				'error' => [
					'errors' => $errors
				]
			];
		} else {
			$json = [
				'method' => 'upload',
				'params' => [
					'folder' => $folder_id,
				],
				'code' => 200,
				'data' => [
					'items' => $items,
					'errors' => $errors,
				]
			];
		}
		break;

	case 'file-rename':
		$name = $request->getPost('name', FILTER_SANITIZE_STRING);

		$file = new File;
		$name = $file->sanitizeFilename($name);
		if ( $file->get($file_id)->setName($name)->isValid() )
		{
			$json = [
				'method' => 'file.rename',
				'params' => [
					'name' => $name,
					'folder_id' => $file->folder_id,
				],
				'code' => 200,
				'data' => [
					'name' => $file->name,
					'normalized' => $file->normalized,
					'folder_id' => $file->folder_id,
					'message' => "File renamed to '". $file->name . "' ",
				]
			];
		} else {
			oops(400, "unable to rename", $file->errors());
		}
		break;

	case 'file-delete':
		$file = new File;

		$result = $file->get($file_id)->delete()->isValid();
		if ( $result ) {
			$json = [
				'method' => 'file.delete',
				'params' => [
					'file_id' => $file_id
				],
				'code' => 200,
				'data' => [
					'folder_id' => $file->folder_id,
					'message' => "File '". $file->name ."' deleted",
				]
			];
		} else {
			oops(400,  $file->name . " cannot be deleted due to an error", $file->errors());
		}
		break;

	case 'file-move':
		$file = new File;
	
		if ( $file->get($file_id)->move($folder_id)->isValid() ) {
			$json = [
				'method' => 'file.move',
				'params' => [
					'file_id' => $file_id
				],
				'code' => 200,
				'data' => [
					'message' => "File '". $file->name ."' moved",
				]
			];
		} else {
			oops(400, "Unable to move file", $file->errors());
		}
		break;

	case 'folder-create':
		$name = $request->getPost('name', FILTER_SANITIZE_STRING);
		if ( !$folder_id ) {
			$folder_id = 1;
		}

		$folder = new Folder;
		$folder->name = $name;
		$folder->parent_id = $folder_id;

		if ( $folder->add()->isValid() ) {
			$json = [
				'method' => 'folder.create',
				'params' => [
					'name' => $name,
				],
				'code' => 200,
				'data' => [
					'message' => "Folder '". $folder->name . "' created",
					'folder_id' => $folder->id,
				]
			];
		} else {
			oops(400, "Unable to create folder", $folder->errors());
		}
		break;

	case 'folder-rename':
		$name = $request->getPost('name', FILTER_SANITIZE_STRING);

		$folder = new Folder;
		if ( $folder->get($folder_id)->setName($name)->isValid() ) {
			$json = [
				'method' => 'folder.rename',
				'params' => [
					'id' => $folder->id,
					'name' => $name,
				],
				'code' => 200,
				'data' => [
					'id' => $folder->id,
					'message' => "Folder renamed to ". $folder->name . " created",
				]
			];
		} else {
			oops(400, "Renaming failed", $folder->errors());
		}
		break;

	case 'folder-delete':
		$folder = new Folder;
		$result = $folder->get($folder_id)->delete()->isValid();

		if ( $result ) {
			$json = [
				'method' => 'folder.delete',
				'params' => [
					'folder_id' => $folder->id
				],
				'code' => 200,
				'data' => [
					'message' => "Folder '". $folder->name ."' deleted",
				]
			];
		} else {
			oops(400, "Failed to delete folder", $folder->errors());
		}
		break;

	case 'folder-move':
		$to_folder = $request->getPost("to_folder");
		$folder = new Folder;

		if ( $folder->get($folder_id)->move($to_folder)->isValid() ){
			$json = [
				'method' => 'folder.move',
				'params' => [
					'folder' => $folder_id,
					'to_folder' => $to_folder,
				],
				'code' => 200,
				'data' => [
					'message' => "Folder '". $folder->name ."' moved",
				]
			];
		} else {
			oops(400, "Failed to move folder", $folder->errors());
		}
		break;

	case 'get-all':
		$folder = new Folder;
		$file = new File;

		$json = [
			'method' => 'get.all',
			'code' => 200,
			'data' => [
				'breadcrumbs' => [],
				'items' => array_merge($folder->all(), $file->all()),
			]
		];
		break;

	case 'get-files-all':
		$files = new File;

		$json = [
			'method' => 'get.files.all',
			'code' => 200,
			'data' => [
				// 'breadcrumbs' => [],
				'items' => $files->all(),
			]
		];
		break;

	// https://stackoverflow.com/questions/6802539/hierarchical-tree-database-for-directories-in-filesystem
	// https://www.slideshare.net/billkarwin/models-for-hierarchical-data
	case 'get-folders-all':
		$folder = new Folder;
		$folder_id = 1;

		$json = [
			'method' => 'get.folders.all',
			'code' => 200,
			'data' => [
				'items' => $folder->setId($folder_id)->setShowSelf(true)->descendents(),
				'nested' => $folder->setId($folder_id)->setShowSelf(true)->nested(),
			],
		];
		break;

	case 'get-folder-all':
		$folder = new Folder;
		$folder_id = (empty($folder_id)) ? 1 : $folder_id;

		$json = [
			'method' => 'get.folders.all',
			'code' => 200,
			'params' => [
				'folder' => $folder_id
			],
			'data' => [
				'items' => $folder->setId($folder_id)->setShowSelf(true)->descendents(),
				'nested' => $folder->setId($folder_id)->setShowSelf(true)->nested(),
			],
		];
		break;

	case 'get-folder-folders':
		$folder = new Folder;
		$folder->id = $folder_id;

		$json = [
			'method' => 'get.folder.folders',
			'code' => 200,
			'params' => [
				'folder' => $folder_id,
			],
			'data' => [
				'items' => $folder->setShowSelf(true)->descendents($folder_id, true),
				'nested' => $folder->setShowSelf(true)->nested($folder_id, true),
			],
		];
		break;

	case 'get-folder-files':
		if (empty($folder_id)){
			$folder_id = 1;
		}
		$folder = new Folder;

		$json = [
			'method' => 'get.folder.files',
			'code' => 200,
			'params' => [
				'folder' => $folder_id
			],
			'data' => [
				'breadcrumbs' => $folder->setShowSelf(true)->breadcrumbs(),
				'items' => array_merge(
					$folder->setId($folder_id)->children(),
					$folder->setId($folder_id)->files()
				),
			]
		];
	break;

	case 'download-file':
		$file = new File;
		$file->get($file_id);

		if( !$file->id ) {
			die("file does not exists");
		}
		$file_pointer = CONFIG['base_path_store'] . $file->saved_as;
		if ( file_exists($file_pointer) ) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'. basename($file->name) .'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . $file->size);
			readfile($file_pointer);
			exit;
		} else {
			die("File not found on the server");
		}
		break;

	case 'download-zip-folder':
		$full_path_to_zip = 'tmp/'. date("YmdHis") .'.zip';

		$zip = new ZipArchive;
		if ( $zip->open($full_path_to_zip, ZipArchive::CREATE) === TRUE ) {
			$folder = new Folder;
			$folder->get($folder_id);
			$counter = 0;
			
			$fnRecursiveFolder = function($node, $current_relative_path) use (&$fnRecursiveFolder, $zip, $counter) {
				$folder = new Folder;
				$files = $folder->setId($node['id'])->files();
				foreach ($files as $file) {
					$name = basename($file['name'], '.'. $file['extension']);
					$zip->addFile( CONFIG['base_path_store'] . $file['saved_as'], $current_relative_path . $name . '.'. $file['extension']);
					// echo CONFIG['base_path_store'] . $file['saved_as'] .' = '. $current_relative_path . basename($file['name']);
					// echo '<br>';
					$counter++;
				}
				if ( array_key_exists('children', $node) ) {
					foreach ($node['children'] as $key => $sub_node) {
						
						$fnRecursiveFolder($sub_node, $current_relative_path . $sub_node['name'] . '/');
					}
				}
				return true;
			};
			$current_relative_path = $folder->name . '/';
			$nested = $folder->setShowSelf(true)->nested();
			$result = $fnRecursiveFolder($nested, $current_relative_path);
			$zip->close();

			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=' . CONFIG['download_zipname']);
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($full_path_to_zip));
			readfile($full_path_to_zip);
			@unlink($full_path_to_zip);
			die();
		} else {
			die('failed');
		}
		break;

	case 'checked-zip-download':
		$zip_file = date("YmdHis") .'.zip';
		$full_path_to_zip = 'tmp/'. $zip_file;

		$zip = new ZipArchive;
		if ( $zip->open($full_path_to_zip, ZipArchive::CREATE) === TRUE ) {
			
			$fnRecursiveFolder = function($node, $current_relative_path) use (&$fnRecursiveFolder, $zip) {
				$folder = new Folder;
				$files = $folder->setId($node['id'])->files();
				foreach ($files as $file) {
					$name = basename($file['name'], '.'. $file['extension']);
					$zip->addFile( CONFIG['base_path_store'] . $file['saved_as'], $current_relative_path . $name . '.'. $file['extension']);
				}
				if ( array_key_exists('children', $node) ) {
					foreach ($node['children'] as $key => $sub_node) {
						
						$fnRecursiveFolder($sub_node, $current_relative_path . $sub_node['name'] . '/');
					}
				}
				return true;
			};
			if ( isset($_GET['files_ids']) && is_array($_GET['files_ids']) ) {
				$files_ids = array_filter( $_GET['files_ids'], 'strlen' );
				$files_ids = array_map(function($value) {
					return intval($value);
				}, $files_ids);
				$files = new File;
				$files->getBatch($files_ids);
				foreach ($files as $key => $file) {
					$name = basename($file['name'], '.'. $file['extension']);
					$zip->addFile( CONFIG['base_path_store'] . $file['saved_as'], $name . '.'. $file['extension']);
				}
			}
			if ( isset($_GET['folders_ids']) && is_array($_GET['folders_ids']) ) {
				$folders_ids = array_filter( $_GET['folders_ids'], 'strlen' );
				$folders_ids = array_map(function($value) {
					return intval($value);
				}, $folders_ids);
				foreach ($folders_ids as $folder_id) {
					$folder = new Folder;
					$folders_nested = $folder->setId($folder_id)->setShowSelf(true)->nested();
					$current_relative_path = $folders_nested['name'] . '/';
					$result = $fnRecursiveFolder($folders_nested, $current_relative_path);
				}
			}
			$zip->close();

			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=' . CONFIG['download_zipname']);
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($full_path_to_zip));
			readfile($full_path_to_zip);
			@unlink($full_path_to_zip);
			die();
		} else {
			die("Failed to create zip file");
		}
		break;

	case 'checked-delete':
		$result_files = [];
		$result_folders = [];

		$files_ids = isset($_POST['files_ids']) ? $_POST['files_ids'] : [];
		$folders_ids = isset($_POST['folders_ids']) ? $_POST['folders_ids'] : [];

		$files_ids = filter_ids_array( $files_ids );
		$folders_ids = filter_ids_array( $folders_ids );

		$file = new File;
		$result_files = $file->deleteBatch($files_ids)->isValid();
		$folder = new Folder;
		foreach ($folders_ids as $folder_id) {
			$result_folders[$folder_id] = $folder->setId($folder_id)->delete($folder_id)->isValid();
		}
		$json = [
				'method' => 'checked.delete',
				'params' => [
					'files_ids' => $files_ids,
					'folders_ids' => $folders_ids,
				],
				'code' => 200,
				'data' => [
					'result_files' => $result_files,
					'result_folders' => $result_folders,
					'message' => "Results",
				]
		];
	break;

	case 'checked-move':
		$result_files = [];
		$result_folders = [];
		$to_folder = intval($_POST['to_folder']);
		$files_ids = isset($_POST['files_ids']) ? $_POST['files_ids'] : [];
		$folders_ids = isset($_POST['folders_ids']) ? $_POST['folders_ids'] : [];

		$files_ids = filter_ids_array( $files_ids );
		$folders_ids = filter_ids_array( $folders_ids );

		foreach ($files_ids as $file_id) {
			$file = new File;
			$result_files[$file_id] = $file->setId($file_id)->move($to_folder)->isValid();
		}
		foreach ($folders_ids as $folder_id) {
			$folder = new Folder;
			$result_folders[$folder_id] = $folder->setId($folder_id)->move($to_folder)->isValid();
		}
		$json = [
				'method' => 'checked-move',
				'params' => [
					'files_ids' => $files_ids,
					'folders_ids' => $folders_ids,
				],
				'code' => 200,
				'data' => [
					'result_files' => $result_files,
					'result_folders' => $result_folders,
					'message' => "Results",
				]
		];
	break;

	default:
		$json = [
			'method' => 'none',
			'code' => 200,
			'params' => [
			],
			'data' => [
				'message' => "Didn't ask anything."
			]
		];
}

ob_start('ob_gzhandler');
http_response_code($json['code']);
header('Content-type: application/json');
echo json_encode($json);
