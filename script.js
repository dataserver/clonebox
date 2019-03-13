$(document).ready(function () {
// script.js
// http://patorjk.com/software/taag/#p=display&c=c&f=Colossal&t=text
const APP_TITLE = "Clonebox";
const DEFAULT_PREFERENCES = {
	grid_size : "list",
	grid_columns : 1,
	sort_group : "name",
	sort_order  : "asc",
};
var DATABASE_FOLDERS_TREE;
var DATABASE_FOLDERS;
var DATABASE_FILES = [];
var USER_PREFERENCES;
var CURRENT_FOLDER_ID = 1;
var UPLOAD_FILES_DATA = [];
var CHECKED_TOTAL_ITEMS = 0;

	USER_PREFERENCES = getUser();
	if ( typeof storageVals === "undefined" || !storageVals || default_options.length != USER_PREFERENCES.length ) {
		USER_PREFERENCES = _.merge(DEFAULT_PREFERENCES, USER_PREFERENCES);
		updateUser();	
	}
	toastr.options = {
		"closeButton": true,
		"debug": false,
		"newestOnTop": false,
		"progressBar": false,
		"positionClass": "toast-bottom-center",
		"preventDuplicates": false,
		"onclick": null,
		"showDuration": "500",
		"hideDuration": "1000",
		"timeOut": "1000",
		"extendedTimeOut": "1000",
		"showEasing": "swing",
		"hideEasing": "linear",
		"showMethod": "fadeIn",
		"hideMethod": "fadeOut"
	}

/***
 *             888 d8b      888                  
 *             888 Y8P      888                  
 *             888          888                  
 *    .d8888b  888 888  .d88888  .d88b.  888d888 
 *    88K      888 888 d88" 888 d8P  Y8b 888P"   
 *    "Y8888b. 888 888 888  888 88888888 888     
 *         X88 888 888 Y88b 888 Y8b.     888     
 *     88888P  888 888  "Y88888  "Y8888  888     
 *                                               
 *                                               
 *                                               
 */
// $("#sidebar").mCustomScrollbar({
// 	 theme: "minimal-dark"
// });
$("#js-sidemenu-open").click(function(e){
	e.stopPropagation();
	e.preventDefault();

	$("#sidebar").toggleClass("d-none d-lg-block");
	$("#overlay-sidebar-under").addClass("active");
	$("#sidebar").toggleClass("active");
});

$("#js-sidemenu-close, #overlay-sidebar-under").on("click", function (e) {
	e.stopPropagation();
	e.preventDefault();

	// hide sidebar
	$("#sidebar").addClass("d-none d-lg-block");
	// hide overlay
	$("#overlay-sidebar-under").removeClass("active");
	$("#sidebar").removeClass("active");
});

/***
 *                      888                        888 
 *                      888                        888 
 *                      888                        888 
 *    888  888 88888b.  888  .d88b.   8888b.   .d88888 
 *    888  888 888 "88b 888 d88""88b     "88b d88" 888 
 *    888  888 888  888 888 888  888 .d888888 888  888 
 *    Y88b 888 888 d88P 888 Y88..88P 888  888 Y88b 888 
 *     "Y88888 88888P"  888  "Y88P"  "Y888888  "Y88888 
 *             888                                     
 *             888                                     
 *             888                                     
 */

// https://makitweb.com/drag-and-drop-file-upload-with-jquery-and-ajax/
// preventing page from redirecting
$("html").on("dragover", function(e) {
	e.preventDefault();
	e.stopPropagation();

	$("#overlay-dropzone").show();
});

$("html").on("drop", function(e) {
	e.preventDefault();
	e.stopPropagation();
});

// Drag enter
$("#overlay-dropzone").on("dragenter", function (e) {
	e.stopPropagation();
	e.preventDefault();
});

// Drag Leave
$("#overlay-dropzone").on("dragleave", function (e) {
	e.stopPropagation();
	e.preventDefault();

	$("#overlay-dropzone").hide();
});

// Drag over
$("#overlay-dropzone").on("dragover", function (e) {
	e.stopPropagation();
	e.preventDefault();
});

$(document).on("click", ".js-select-folder", function(e){
	let folder_id = $(this).attr("data-id");
	
	select_folder(folder_id);
});

$(document).on("click", ".js-form-upload-btn-upload", function(e){
	e.preventDefault();

	$("#form-upload-input-file").click();
});

// http://embed.plnkr.co/gtsL0c2uCoLFkQ4R25nb/
var dropzone = document.getElementById("overlay-dropzone");
dropzone.addEventListener('drop', async function(event) {
    event.preventDefault();
    let items = await getAllFileEntries(event.dataTransfer.items);

	UPLOAD_FILES_DATA = [];
	items.files.forEach(function(item, idx) {
		let folder_path = item.fullPath.replace(item.name, "");
		let folder_name = folder_path.match(/([^\/]*)\/*$/)[1];

		item.file(function(file) {
			if ( file != null ) {
				file.folder_name = folder_name;
				file.folder_path = folder_path;
				UPLOAD_FILES_DATA.push(file); // file exist, but don't append
			}
		});
	});
	
	$("#overlay-dropzone").hide();
	_render_folder_selector(CURRENT_FOLDER_ID);
	$("#overlay-select-directory-title").html("Upload to...");
	$("#overlay-select-directory-buttons-submit").html(`
		<button type="button" id="upload-to-folder-btn-submit" class="btn btn-primary">Upload</button>
	`);
	$("#overlay-select-directory").show();
});

$("#form-upload-input-file").change(function(e) {
	e.preventDefault();

	UPLOAD_FILES_DATA = [];
	for (let i = 0, len=$(this).get(0).files.length; i < len; i++) {
		UPLOAD_FILES_DATA.push($(this).get(0).files[i]);
	}
	_render_folder_selector(CURRENT_FOLDER_ID);
	$("#overlay-select-directory-title").html("Upload to...");
	$("#overlay-select-directory-buttons-submit").html(`
		<button type="button" id="upload-to-folder-btn-submit" class="btn btn-primary">Upload</button>
	`);
	$("#overlay-select-directory").show();
});

$(document).on("click", "#upload-to-folder-btn-submit", function(e) {
	e.preventDefault();
	var folder_id = $("#overlay-select-directory-input-id").val();

	$("#overlay-select-directory-input-id").val("");
	uploadFileTo(folder_id);
	$("#overlay-select-directory").hide();
});

$("#overlay-select-directory-buttons-btn-cancel").click(function(e) {
	e.preventDefault();

	UPLOAD_FILES_DATA = [];
	$("#overlay-select-directory-buttons-submit").html("");
	$("#overlay-select-directory-input-id").val("");
	$("#overlay-select-directory").hide();
});

// Drop handler function to get all files
async function getAllFileEntries(dataTransferItemList) {
	let fileEntries = [];
	let dirEntries = [];
	// Use BFS to traverse entire directory/file structure
	let queue = [];

	// Unfortunately dataTransferItemList is not iterable i.e. no forEach
	for (let i = 0; i < dataTransferItemList.length; i++) {
		queue.push(dataTransferItemList[i].webkitGetAsEntry());
	}
	while (queue.length > 0) {
		let entry = queue.shift();
		if ( entry.isFile ) {
			fileEntries.push(entry);
		} else if ( entry.isDirectory ) {
			dirEntries.push(entry);
			queue.push(...await readAllDirectoryEntries(entry.createReader()));
		}
	}

	return {
		files: fileEntries,
		dirs: dirEntries,
	};
}

// Get all the entries (files or sub-directories) in a directory 
// by calling readEntries until it returns empty array
async function readAllDirectoryEntries(directoryReader) {
	let entries = [];
	let readEntries = await readEntriesPromise(directoryReader);

	while (readEntries.length > 0) {
		entries.push(...readEntries);
		readEntries = await readEntriesPromise(directoryReader);
	}

	return entries;
}

// Wrap readEntries in a promise to make working with readEntries easier
// readEntries will return only some of the entries in a directory
// e.g. Chrome returns at most 100 entries at a time
async function readEntriesPromise(directoryReader) {

	try {

		return await new Promise((resolve, reject) => {
			directoryReader.readEntries(resolve, reject);
		});
	} catch ( err ) {
		console.log( err );
	}
}

// posts each file separately
function uploadFileTo(folder, total_to_upload = false, idx = 0) {

	if ( !total_to_upload ) { // first run
		total_to_upload = UPLOAD_FILES_DATA.length;
	}
	if ( idx < total_to_upload ) {
		let name = UPLOAD_FILES_DATA[idx].name;
		let html = `
				<div id="progress-${idx}" class="my-3">
					<div class="row">
						<div class="progress col-9" style="height:28px;">
							<div id="progress-bar-${idx}" class="progress-bar mx-2" role="progressbar" style="" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
						<div class="col">
							<button type="button" class="js-cancel-this-upload btn btn-danger btn-sm" data-index="${idx}"><i class="fa fa-ban" aria-hidden="true"></i> cancel</button>
						</div>
					</div>
				</div>
		`;
		let formData = new FormData();
			formData.append("action", "file-upload");
			formData.append("upload_files[]", UPLOAD_FILES_DATA[idx]); //append the next file
			// formData.append("folder", folder);
			formData.append("parent_id", folder);
		
		if ( typeof UPLOAD_FILES_DATA[idx].folder_path != "undefined") {
			formData.append("children_folder_path", UPLOAD_FILES_DATA[idx].folder_path);
		}
		if ( typeof UPLOAD_FILES_DATA[idx].folder_name != "undefined") {
			formData.append("children_folder_name", UPLOAD_FILES_DATA[idx].folder_name);
		}
		$("#progressbars").append( html );
		$.ajax({
			url: "server.php",
			type: "POST",
			data: formData, 
			cache: false,
			contentType: false,
			processData: false,
			xhr: function(){
				var xhr = $.ajaxSettings.xhr() ;
				xhr.upload.onprogress = function(data) {
					var perc = Math.round((data.loaded / data.total) * 100);
					$("#progress-bar-"+ idx ).text(" "+ name +" - "+ perc + "%");
					$("#progress-bar-"+ idx ).attr("aria-value", perc);
					$("#progress-bar-"+ idx ).css("width", perc + "%");
				};

				return xhr;
			},
			success: function(response){
				var items = response.data.items;
				var errors = response.data.errors;

				for (var i = items.length - 1; i >= 0; i--) {
					toastr["success"](items[i].message);
				}
				for (var i = errors.length - 1; i >= 0; i--) {
					toastr["error"](errors[i].message);
				}
				CURRENT_FOLDER_ID = response.params.folder;
				$("#progress-"+ idx ).remove();
			},
			error: function(xhr, textStatus) {
				if ( textStatus == "abort" ) {
					$("#progress-"+ idx ).remove();
				} else {
					toastr["error"](xhr.responseJSON.error.message);
				}
			},
			beforeSend: function(xhr) {
				$(`.js-cancel-this-upload[data-index='${idx}']`).click(function(){
					xhr.abort();
				});
				uploadFileTo(folder, total_to_upload, idx + 1) // begins next progress bar
			}
		}).done(function(){
			syncBrowserAndDatabaseInfo();
		});
	}
}

/***
 *    8888888888 8888888 888      8888888888 
 *    888          888   888      888        
 *    888          888   888      888        
 *    8888888      888   888      8888888    
 *    888          888   888      888        
 *    888          888   888      888        
 *    888          888   888      888        
 *    888        8888888 88888888 8888888888 
 *                                           
 *                                           
 *                                           
 */

$("#filelist").on("click", ".js-file-rename", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");
	let name = $(this).attr("data-name");
	let file_ext = name.split(".").pop();
	// let file_name = name.split(".").slice(0, -1).join(".");

	bootbox.prompt({
		title: "Rename file",
		message: "Please inform the new name for <b>"+ name +"</b>?",
		placeholder: "New name." + file_ext,
		required: "required",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Rename",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let name = result;
				let data = {
					action: "file-rename",
					file: id,
					name: name,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response){
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});


$("#filelist").on("click", ".js-file-delete", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");
	let name = $(this).attr("data-name");

	bootbox.confirm({
		title: "Delete file?",
		message: "Do you want to delete <b>"+ name +"</b>?",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Delete",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let data = {
					action: "file-delete",
					file: id,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response){
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});

$("#filelist").on("click", ".js-file-move", function(e) {
	e.preventDefault();
	let name = $(this).attr("data-name");
	let id = $(this).attr("data-id");

	_render_folder_selector(CURRENT_FOLDER_ID);
	$("#overlay-select-directory-title").html("Move to...");
	$("#overlay-select-directory-buttons-submit").html(`
		<button type="button" id="move-file-to-folder-btn-submit" data-id="`+ id +`" class="btn btn-primary">Move</button>
	`);
	$("#overlay-select-directory").show();
});
$(document).on("click", "#move-file-to-folder-btn-submit", function(e){
	e.preventDefault();
	var folder_id = $("#overlay-select-directory-input-id").val();
	var file_id = $(this).attr("data-id");

	$("#overlay-select-directory").hide();
	CURRENT_FOLDER_ID = folder_id;
	$.ajax({
		url: "server.php",
		type: "post",
		data: {
			"action" : "file-move",
			"folder" : folder_id,
			"file"   : file_id,
		},
		cache: false,
		dataType: "json",
		success: function(response){
			toastr["success"](response.data.message);
			syncBrowserAndDatabaseInfo();
		},
		error: function(xhr, status) {
			toastr["error"](xhr.responseJSON.error.message);
		}
	});
});


/***
 *    8888888888 .d88888b.  888      8888888b.  8888888888 8888888b.  
 *    888       d88P" "Y88b 888      888  "Y88b 888        888   Y88b 
 *    888       888     888 888      888    888 888        888    888 
 *    8888888   888     888 888      888    888 8888888    888   d88P 
 *    888       888     888 888      888    888 888        8888888P"  
 *    888       888     888 888      888    888 888        888 T88b   
 *    888       Y88b. .d88P 888      888  .d88P 888        888  T88b  
 *    888        "Y88888P"  88888888 8888888P"  8888888888 888   T88b 
 *                                                                    
 *                                                                    
 *                                                                    
 */

$(document).on("click", ".js-folder-view", function(e) {
	e.stopPropagation();
	e.preventDefault();
	let id = $(this).attr("data-id");
	let name = $(this).attr("data-name");

	CURRENT_FOLDER_ID = id;
	updateHistory({id:id, name:name});
	render( CURRENT_FOLDER_ID );
	if ( $("#overlay-sidebar-under").hasClass("active") ) {
		$("#overlay-sidebar-under").trigger( "click" );
	}
})

$(document).on("click", ".js-folder-create", function(e) {
	e.preventDefault();

	bootbox.prompt({
		title: "Create new folder",
		message: "Name this folder",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Create",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let name = result;
				let data = {
					action: "folder-create",
					name : name,
					folder: CURRENT_FOLDER_ID,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response){
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});

$("#filelist").on("click", ".js-folder-rename", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");
	let name = $(this).attr("data-name");

	bootbox.prompt({
		title: "Rename file",
		message: "Please inform the new name for the folder <b>"+ name +"</b>?",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Rename",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let name = result;
				let data = {
					action: "folder-rename",
					folder: id,
					name: name,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response){
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});

$("#filelist").on("click", ".js-folder-delete", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");
	let name = $(this).attr("data-name");

	bootbox.confirm({
		title: "Delete folder and all files inside?",
		message: "Are sure to delete the folder <b>"+ name +"</b> and all sub-directories and files inside them?",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Delete",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let data = {
					action: "folder-delete",
					folder: id,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response){
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});

$("#filelist").on("click", ".js-folder-move", function(e) {
	e.preventDefault();
	let name = $(this).attr("data-name");
	let id = $(this).attr("data-id");
		id = parseInt(id);
	let folders = getChildren( id , DATABASE_FOLDERS);
	let disabled_folders = array_pluck(folders, "id");
		disabled_folders.push(id);

	_render_folder_selector(CURRENT_FOLDER_ID, disabled_folders);
	$("#overlay-select-directory-title").html("Move to...");
	$("#overlay-select-directory-buttons-submit").html(`
		<button type="button" id="move-folder-to-folder-btn-submit" data-id="`+ id +`" class="btn btn-primary">Move</button>
	`);
	$("#overlay-select-directory").show();
});

$(document).on("click", "#move-folder-to-folder-btn-submit", function(e) {
	e.preventDefault();
	var to_folder = $("#overlay-select-directory-input-id").val();
	var folder_id = $(this).attr("data-id");

	$("#overlay-select-directory").hide();
	CURRENT_FOLDER_ID = to_folder;
	$.ajax({
		url: "server.php",
		type: "post",
		data: {
			"action" : "folder-move",
			"folder" : folder_id,
			"to_folder" : to_folder,
		},
		cache: false,
		dataType: "json",
		success: function(response){
			toastr["success"](response.data.message);
			syncBrowserAndDatabaseInfo();
		},
		error: function(xhr, status) {
			toastr["error"](xhr.responseJSON.error.message);
		}
	});
});



/***
 *         888                                 888                        888 
 *         888                                 888                        888 
 *         888                                 888                        888 
 *     .d88888  .d88b.  888  888  888 88888b.  888  .d88b.   8888b.   .d88888 
 *    d88" 888 d88""88b 888  888  888 888 "88b 888 d88""88b     "88b d88" 888 
 *    888  888 888  888 888  888  888 888  888 888 888  888 .d888888 888  888 
 *    Y88b 888 Y88..88P Y88b 888 d88P 888  888 888 Y88..88P 888  888 Y88b 888 
 *     "Y88888  "Y88P"   "Y8888888P"  888  888 888  "Y88P"  "Y888888  "Y88888 
 *                                                                            
 *                                                                            
 *                                                                            
 */

$("#filelist").on("click", ".js-file-download", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");

	window.location = "server.php?action=download-file&file=" + id;
});

$("#filelist").on("click", ".js-folder-download", function(e) {
	e.preventDefault();
	let id = $(this).attr("data-id");

	window.location = "server.php?action=download-zip-folder&folder=" + id;
});



/***
 *             888                        888                    888 
 *             888                        888                    888 
 *             888                        888                    888 
 *     .d8888b 88888b.   .d88b.   .d8888b 888  888  .d88b.   .d88888 
 *    d88P"    888 "88b d8P  Y8b d88P"    888 .88P d8P  Y8b d88" 888 
 *    888      888  888 88888888 888      888888K  88888888 888  888 
 *    Y88b.    888  888 Y8b.     Y88b.    888 "88b Y8b.     Y88b 888 
 *     "Y8888P 888  888  "Y8888   "Y8888P 888  888  "Y8888   "Y88888 
 *                                                                   
 *                                                                   
 *                                                                   
 */
function uncheck_all() {

	$("INPUT.js-input-checkbox").prop("checked" , false).change();
}

$(document).on("change","#js-checkbox-all", function(e) {

	if (this.checked) {
		$(".grid-item").addClass("selected");
	} else {
		$(".grid-item").removeClass("selected");
	}
	$("INPUT.js-input-checkbox").prop("checked" , this.checked).change(); // change() to trigger onchange event
});

$(document).on("change", "#filelist .js-input-checkbox", function(e) {
	e.preventDefault();
	let type = $(this).attr("data-type");
	let id = $(this).attr("data-id");
	let checked = $(this).is(":checked");
	
	if ( checked ) {
		CHECKED_TOTAL_ITEMS += 1;
		$(`.grid-item[data-type="${type}"][data-id="${id}"]`).addClass("selected");
	} else {
		CHECKED_TOTAL_ITEMS -= 1;
		$(`.grid-item[data-type="${type}"][data-id="${id}"]`).removeClass("selected");
	}
	if (CHECKED_TOTAL_ITEMS > 0) {
		$("#section-checkbox-action .js-checked-download").show();
		$("#section-checkbox-action .js-checked-move ").show();
		$("#section-checkbox-action .js-checked-delete").show();
	} else {
		$("#section-checkbox-action .js-checked-download").hide();
		$("#section-checkbox-action .js-checked-move ").hide();
		$("#section-checkbox-action .js-checked-delete").hide();
	}
});

$(document).on("click", ".js-checked-delete", function(e) {
	e.preventDefault();
	let array_folders_ids = [];
	let array_files_ids = [];

	$(".js-input-checkbox:checkbox:checked").each(function(e) {
		if ( $(this).attr("name") == "folders[]" ) {
			array_folders_ids.push( parseInt($(this).val()) );
		}
		if ( $(this).attr("name") == "files[]" ) {
			array_files_ids.push( parseInt($(this).val()) );
		}
	})
	bootbox.confirm({
		title: "Delete selected folders/files?",
		message: "Are sure to delete the selected files and/or folders?",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Delete",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let data = {
					action: "checked-delete",
					folders_ids: array_folders_ids,
					files_ids: array_files_ids,
				};

				$.ajax({
					url: "server.php",
					type: "post",
					data: data,
					cache: false,
					dataType: "json",
					success: function(response) {
						toastr["success"](response.data.message);
						syncBrowserAndDatabaseInfo();
					},
					error: function(xhr, status) {
						toastr["error"](xhr.responseJSON.error.message);
					}
				});
			}
		}
	});
});

$(document).on("click", ".js-checked-download", function(e) {
	e.preventDefault();
	let array_folders_ids = [];
	let array_files_ids = [];

	$(".js-input-checkbox:checkbox:checked").each(function(e) {
		if ( $(this).attr("name") == "folders[]" ) {
			array_folders_ids.push( parseInt($(this).val()) );
		}
		if ( $(this).attr("name") == "files[]" ) {
			array_files_ids.push( parseInt($(this).val()) );
		}
	})
	bootbox.confirm({
		title: "Download selected folders/files?",
		message: "Are sure to download the selected files and/or folders?",
		size: "large",
		swapButtonOrder: true,
		buttons: {
			confirm: {
				label: "Download",
				className: "btn-primary"
			},
			cancel: {
				label: "Cancel",
				className: "btn-light"
			}
		},
		callback: function(result) {
			if ( result ) {
				let data = {
					action: "checked-zip-download",
					folders_ids: array_folders_ids,
					files_ids: array_files_ids,
				};
				let query_string = $.param( data );

				uncheck_all();
				window.location = "server.php?" + query_string;
			}
		}
	});
});

$("#filelist").on("click", ".js-checked-move", function(e) {
	e.preventDefault();
	let disabled_folders = [];
	let array_folders_ids = [];
	let array_files_ids = [];

	$(".js-input-checkbox:checkbox:checked").each(function(e) {
		if ( $(this).attr("name") == "folders[]" ) {
			array_folders_ids.push( parseInt($(this).val()) );
		}
		if ( $(this).attr("name") == "files[]" ) {
			array_files_ids.push( parseInt($(this).val()) );
		}
	})
	array_folders_ids.forEach(function(value, index){
		let tmp_folders = getChildren( value , DATABASE_FOLDERS);
		let tmp_disabled_folders = array_pluck(tmp_folders, "id");

		disabled_folders.push(value);
		disabled_folders = disabled_folders.concat(tmp_disabled_folders);
	});
	_render_folder_selector(CURRENT_FOLDER_ID, disabled_folders);
	let form_hidden_folders = JSON.stringify(array_folders_ids);
	let form_hidden_files = JSON.stringify(array_files_ids);
	$("#overlay-select-directory-title").html("Move to...");
	$("#overlay-select-directory-buttons-submit").html(`
		<input type="hidden" name="folders" value="${form_hidden_folders}">
		<input type="hidden" name="files" value="${form_hidden_files}">
		<button type="button" id="move-checked-to-folder-btn-submit"class="btn btn-primary">Move</button>
	`);
	$("#overlay-select-directory").show();
});

$(document).on("click", "#move-checked-to-folder-btn-submit", function(e) {
	e.preventDefault();
	let to_folder = $("#overlay-select-directory-input-id").val();
	let folders_ids = JSON.parse( $(`#overlay-select-directory-buttons-submit INPUT[name="folders"]`).val() );
	let files_ids = JSON.parse( $(`#overlay-select-directory-buttons-submit INPUT[name="files"]`).val() );

	$("#overlay-select-directory").hide();
	CURRENT_FOLDER_ID = to_folder;
	$.ajax({
		url: "server.php",
		type: "post",
		data: {
			"action" : "checked-move",
			"to_folder" : to_folder,
			"files_ids" : files_ids,
			"folders_ids" : folders_ids,
		},
		cache: false,
		dataType: "json",
		success: function(response){
			toastr["success"](response.data.message);
			syncBrowserAndDatabaseInfo();
		},
		error: function(xhr, status) {
			toastr["error"](xhr.responseJSON.error.message);
		}
	});
});


/***
 *             d8b                                 
 *             Y8P                                 
 *                                                 
 *    888  888 888  .d88b.  888  888  888 .d8888b  
 *    888  888 888 d8P  Y8b 888  888  888 88K      
 *    Y88  88P 888 88888888 888  888  888 "Y8888b. 
 *     Y8bd8P  888 Y8b.     Y88b 888 d88P      X88 
 *      Y88P   888  "Y8888   "Y8888888P"   88888P  
 *                                                 
 *                                                 
 *                                                 
 */

$(document).on("click", ".js-grid-view", function(e) {
	e.preventDefault();
	let grid_size = $(this).attr("data-grid");
	let grid_columns = $(this).attr("data-columns");

	USER_PREFERENCES.grid_size = grid_size;
	USER_PREFERENCES.grid_columns = grid_columns;
	updateUser();
	render( CURRENT_FOLDER_ID );
});

$(document).on("click", ".js-grid-sort-group", function(e) {
	e.preventDefault();
	let sort_group = $(this).attr("data-group");
	let title = $(this).attr("data-title");

	USER_PREFERENCES.sort_group = sort_group;
	updateUser();
	$(".js-grid-sort-group").removeClass("active");
	$(this).addClass("active");
	$("#button-sort-title").html( title );
	render( CURRENT_FOLDER_ID );
});

$(document).on("click", ".js-grid-sort-order", function(e) {
	e.preventDefault();
	let sort_order = $(this).attr("data-order");

	USER_PREFERENCES.sort_order = sort_order;
	updateUser();
	$(".js-grid-sort-order").removeClass("active");
	$(this).addClass("active");
	render( CURRENT_FOLDER_ID );
});



/***
 *                              888      d8b          
 *                              888      Y8P          
 *                              888                   
 *     .d8888b .d88b.   .d88b.  888  888 888  .d88b.  
 *    d88P"   d88""88b d88""88b 888 .88P 888 d8P  Y8b 
 *    888     888  888 888  888 888888K  888 88888888 
 *    Y88b.   Y88..88P Y88..88P 888 "88b 888 Y8b.     
 *     "Y8888P "Y88P"   "Y88P"  888  888 888  "Y8888  
 *                                                    
 *                                                    
 *                                                    
 */
function updateUser() {

	Cookies.set("preferences", USER_PREFERENCES);
}

function getUser() {

	return Cookies.getJSON("preferences");
}

function check_grid_preference() {

	$(".js-grid-view").removeClass("active");
	$(".grid-item").removeClass("list large small");
	$(".grid-item").addClass(USER_PREFERENCES.grid_size);
	$(`.js-grid-view[data-grid="${USER_PREFERENCES.grid_size}"]`).addClass("active");

	return false;
}

function check_sort_preferences() {

	$(".js-grid-sort-group").removeClass("active");
	$(".js-grid-sort-order").removeClass("active");
	$(`.js-grid-sort-group[data-group="${USER_PREFERENCES.sort_group}"]`).addClass("active");
	$(`.js-grid-sort-order[data-order="${USER_PREFERENCES.sort_order}"]`).addClass("active");

	return false;
}


/***
 *                                               888      
 *                                               888      
 *                                               888      
 *    .d8888b   .d88b.   8888b.  888d888 .d8888b 88888b.  
 *    88K      d8P  Y8b     "88b 888P"  d88P"    888 "88b 
 *    "Y8888b. 88888888 .d888888 888    888      888  888 
 *         X88 Y8b.     888  888 888    Y88b.    888  888 
 *     88888P"  "Y8888  "Y888888 888     "Y8888P 888  888 
 *                                                        
 *                                                        
 *                                                        
 */

$("#nav-input-search").on("focus", function(e) {

	$("#fixed-top-form-search-col-left").attr("class", "col-1 ml-auto");
	$("#fixed-top-form-search-col-right").attr("class", "col-11 mr-auto");
	$("#nav-search-options").show();
	
});

$("#nav-input-search").on("focusout", function(e) {
	let search = $("#nav-input-search").val();

	if ( search.length > 1 ) {
	} else {
		$("#fixed-top-form-search-col-left").attr("class", "col-8 ml-auto");
		$("#fixed-top-form-search-col-right").attr("class", "col-4 mr-auto");
		$("#nav-search-options").hide();
	}
});

$(document).on("change","#search-only-this-folder", function(e) {
	e.preventDefault();

	$("#nav-input-search").trigger("change");
});

$("#nav-input-search").on("change paste keyup", function(e) {
	e.preventDefault();
	let search = $(this).val();
	let thisFolderOnly = $("#search-only-this-folder").is(":checked");
		search = (typeof(search) != "undefined") ? search : " ";
		search = search.toUpperCase();
	let dataSetToSearch = [];
	let items_found = [];

	if ( thisFolderOnly ){
		let files = getFilesFromFolder(CURRENT_FOLDER_ID);
		let folders = getSubFoldersFromFolder(CURRENT_FOLDER_ID);
		dataSetToSearch = [...folders, ...files];
	} else {
		dataSetToSearch = [...DATABASE_FOLDERS, ...DATABASE_FILES];
	}
	if ( search.length > 1 ) {
		for (let index = 0, len = dataSetToSearch.length; index < len; index++) {
			let normalized = dataSetToSearch[index].name;
			
			if ( normalized.toUpperCase().indexOf( search ) !== -1 ) {
				items_found.push(dataSetToSearch[index]);
			}
		}
		_render_data({
			is_search: true,
			items: items_found,
		});
	} else {
		render();
	}
});


/***
 *    888      d8b          888                                         d8888 8888888b. 8888888 
 *    888      Y8P          888                                        d88888 888   Y88b  888   
 *    888                   888                                       d88P888 888    888  888   
 *    88888b.  888 .d8888b  888888 .d88b.  888d888 888  888          d88P 888 888   d88P  888   
 *    888 "88b 888 88K      888   d88""88b 888P"   888  888         d88P  888 8888888P"   888   
 *    888  888 888 "Y8888b. 888   888  888 888     888  888        d88P   888 888         888   
 *    888  888 888      X88 Y88b. Y88..88P 888     Y88b 888       d8888888888 888         888   
 *    888  888 888  88888P   "Y888 "Y88P"  888      "Y88888      d88P     888 888       8888888 
 *                                                      888                                     
 *                                                 Y8b d88P                                     
 *                                                  "Y88P"                                      
 */
window.addEventListener("popstate", function(e) {
	let id, name;
	let state = history.state;

	if ( state ) {
		id = parseInt(state.folder_id);
		name = state.name;
	} else {
		id = CURRENT_FOLDER_ID;
		name = "Home";
	}
	// console.log("state", state);
	document.title = state.name;
	CURRENT_FOLDER_ID = id;
	render();
});
/*
 * this swallows backspace keys on any non-input element.
 * stops backspace -> back
 */
// https://stackoverflow.com/questions/25806608/how-to-detect-browser-back-button-event-cross-browser
$(document).bind("keydown keypress", function(e){
	let rx = /INPUT|SELECT|TEXTAREA/i;

	if ( e.which == 8 ){ // 8 == backspace
		if ( !rx.test(e.target.tagName) || e.target.disabled || e.target.readOnly ) {
			e.preventDefault();
		}
	}
});

function updateHistory(data = {id: 1, name: "Home"}) {
	let stateObj = {
		folder_id : parseInt(data.id),
		name: data.name,
	};
	// history.pushState(stateObj, "" + stateObj.name +"" , "?title="+ stateObj.name + "&fid="+ stateObj.folder_id +"" );
	history.pushState(stateObj, "" + stateObj.name +"" , "" );
	document.title = ""+ stateObj.name +" - "+ APP_TITLE;
}



/***
 *    d8b          d8b 888    
 *    Y8P          Y8P 888    
 *                     888    
 *    888 88888b.  888 888888 
 *    888 888 "88b 888 888    
 *    888 888  888 888 888    
 *    888 888  888 888 Y88b.  
 *    888 888  888 888  "Y888 
 *                            
 *                            
 *                            
 */
function init() {
	let url = new URL( window.location );
	let folder = parseInt( url.searchParams.get("fid") );
	let title = url.searchParams.get("title");
	let stateObj = {
		folder_id : 1,
		name: "Home",
	};

	if ( folder > 0 ) {
		CURRENT_FOLDER_ID = folder;
	} else {
		// history.replaceState(stateObj, "Title Home" , "?title=Home&fid="+ CURRENT_FOLDER_ID +"");
		history.replaceState(stateObj, "Home" , "");
		document.title = "Home" + " - "+ APP_TITLE;
	}
	syncBrowserAndDatabaseInfo();
}


/***
 *     .d8888b. Y88b   d88P 888b    888  .d8888b.  
 *    d88P  Y88b Y88b d88P  8888b   888 d88P  Y88b 
 *    Y88b.       Y88o88P   88888b  888 888    888 
 *     "Y888b.     Y888P    888Y88b 888 888        
 *        "Y88b.    888     888 Y88b888 888        
 *          "888    888     888  Y88888 888    888 
 *    Y88b  d88P    888     888   Y8888 Y88b  d88P 
 *     "Y8888P"     888     888    Y888  "Y8888P"  
 *                                                 
 *                                                 
 *                                                 
 */

function syncBrowserAndDatabaseInfo() {

	$.when( getDBFiles(), getDBFolders() )
	.done(function() {
		render();
	});
}

function getDBFiles() {

	return $.ajax({
		type: "GET",
		url: "server.php",
		data: {
			action: "get-files-all"
		},
		dataType: "json",
		cache: false,
		success: function (resp) {
			DATABASE_FILES = resp.data.items;
		}// success
	});
}

function getDBFolders() {

	return  $.ajax({
		type: "GET",
		url: "server.php",
		data: {
			action: "get-folders-all"
		},
		dataType: "json",
		cache: false,
		success: function (resp) {
			DATABASE_FOLDERS = resp.data.items;
			DATABASE_FOLDERS_TREE = convert_array_to_nested( resp.data.items );
		}// success
	});

}

function getFilesFromFolder(folder_id = false) {
	let data = [];

	folder_id = (folder_id) ? folder_id : 1;
	if ( DATABASE_FILES.length > 0 ) {
		for (let i = 0, len = DATABASE_FILES.length; i < len; i++) {
			if ( DATABASE_FILES[i].folder_id == folder_id && DATABASE_FILES[i].type=="file" ) {
				data.push(DATABASE_FILES[i]);
			}	    
		}
	}

	return data;
}

function getSubFoldersFromFolder(folder_id = false) {
	let data = [];

	folder_id = (folder_id) ? parseInt(folder_id) : 1;
	if ( DATABASE_FOLDERS.length > 0 ) {
		for (let i = 0, len = DATABASE_FOLDERS.length; i < len; i++) {
			if ( parseInt(DATABASE_FOLDERS[i].parent_id) == folder_id && DATABASE_FOLDERS[i].type=="folder" ) {
				data.push(DATABASE_FOLDERS[i]);
			}	    
		}
	}

	return data;
}






/***
 *                                  888                  
 *                                  888                  
 *                                  888                  
 *    888d888 .d88b.  88888b.   .d88888  .d88b.  888d888 
 *    888P"  d8P  Y8b 888 "88b d88" 888 d8P  Y8b 888P"   
 *    888    88888888 888  888 888  888 88888888 888     
 *    888    Y8b.     888  888 Y88b 888 Y8b.     888     
 *    888     "Y8888  888  888  "Y88888  "Y8888  888     
 *                                                       
 *                                                       
 *                                                       
 */

function render(folder_id = false) {
		folder_id = (folder_id) ? folder_id : CURRENT_FOLDER_ID;
	let folders = getSubFoldersFromFolder(folder_id);
	let files = getFilesFromFolder(folder_id);
	let items = folders;
		items.push(...files);

	_render_data({
		items: items,
	});
}

function _render_data(data) {

	if ( typeof data === "undefined" || !data ) {

		return false;
	}
	if ( data.is_search ) {
		delete data.breadcrumbs;
	} else {
		data.breadcrumbs = breadcrumbs_folders( DATABASE_FOLDERS_TREE , CURRENT_FOLDER_ID);
	}
	data.grid_size = USER_PREFERENCES.grid_size;
	data.grid_columns = USER_PREFERENCES.grid_columns;
	// fix prop size (string to int)
	_.each(data.items, item => item.size = parseInt(item.size, 10));
	// https://lodash.com/docs/4.17.11#orderBy
	// group "name" => normalized
	let group = (USER_PREFERENCES.sort_group =="name") ? "normalized" : USER_PREFERENCES.sort_group;
	data.items = _.orderBy(data.items, ["type", group], ["desc", USER_PREFERENCES.sort_order])
	let targetDiv = document.getElementById("filelist"); 
	let arrayData = data.items;

	switch ( data.grid_size ) {
		case "list":
			let tmpl_thead = $.templates("#template-items-table-thead");
			let tmpl_row   = $.templates("#template-items-table-tr");
			let table = document.createElement("table"); 
			let tbody = document.createElement("tbody");

			table.classList.add("grid-items");
			table.classList.add("table");
			table.insertAdjacentHTML("beforeend", tmpl_thead.render() ); 
			arrayData.forEach((val, idx) => {
				tr = document.createElement("tr");

				tr.classList.add("grid-item");
				tr.setAttribute("data-id", val.id );
				tr.setAttribute("data-type", val.type );
				tr.insertAdjacentHTML("beforeend", tmpl_row.render( val ) ); 

				tbody.appendChild( tr );
			});
			table.appendChild( tbody ); 
			targetDiv.innerHTML = table.outerHTML;
			break;

		case "small":
		case "large":
		default:
			let tmpl_grid_head =  $.templates("#template-items-grid-header");
			let tmpl_card = $.templates("#template-items-grid-card");
			let grid = document.createElement("div");
			let cards_row = false;

			grid.classList.add("grid-items");
			grid.insertAdjacentHTML("beforeend", tmpl_grid_head.render());
			arrayData.forEach((val, idx) => {
				let card = document.createElement("div");
				card.classList.add("col");
				if ( !(idx % data.grid_columns) ) {
					cards_row = document.createElement("div");
					cards_row.classList.add("row");
					grid.appendChild( cards_row );
				}
				card.insertAdjacentHTML("beforeend", tmpl_card.render( val ) ); 
				cards_row.appendChild( card );
			});
			if ( cards_row ) {
				grid.appendChild( cards_row );
			}
			targetDiv.innerHTML = grid.outerHTML;
	}

	if ( typeof(data.breadcrumbs) != "undefined" ) {
		// $("#breadcrumbs").html( tmpl2.render(data) );
		let tmpl_crumbs = $.templates("#template-breadcrumbs");
		let crumbs = document.getElementById("breadcrumbs"); 
		crumbs.innerHTML = tmpl_crumbs.render( data ); 
	}

	check_grid_preference();
	check_sort_preferences();
	convert_unixdatetime_bytes();
}


function _render_folder_selector(folder_id = 1, disabled_folders = []) {
	let html = _buildHTMLselectorTree(DATABASE_FOLDERS_TREE, disabled_folders);
	let selector = document.getElementById("overlay-select-directory-options"); 

	selector.innerHTML = html; 

	select_folder(folder_id);
}

function select_folder(folder_id=1) {

	$("#upload-to-folder .js-select-folder").removeClass("active");
	$(`#upload-to-folder .js-select-folder[data-id="${folder_id}"]`).addClass("active");
	$("#overlay-select-directory-input-id").val(folder_id);

	return true;
}

function _buildHTMLselectorTree(tree, disabled_folders = []) {
	let html = "";

	// Return undefined if there are no array to process;
	// alternatively, it /may/ be appropriate to return an empty UL.
	if ( typeof (tree) == "object" ) {
		tree = Object.values(tree);
	}
	if ( !tree || !tree.length ) {

		return "";
	}
	// use NORMALIZED field "tmp_var.normalized"
	// instead of NAME "tmp_var.name.toLowerCase()"
	tree = _.orderBy(tree, [tmp_var => tmp_var.normalized], ["asc"]);

	for (let i = 0, len=tree.length; i < len; i++) { // Dont use for..in for arrays
		let node = tree[i];
		let space = node.depth * 15;
			// node.id = parseInt(node.id);

		if ( disabled_folders.indexOf(node.id) != -1 ) { // cant be child of descendent

		} else {
			html += `
				<div class="row js-select-folder py-1" data-id="${node.id}">
					<div class="col">
						<span style="margin-left:${space}px;"> ${node.name}</span> 
					</div>
				</div>
			`;
		}
		if (node.children) {
			html += _buildHTMLselectorTree(node.children, disabled_folders);
		}
	}

	return html;
}

// Bytes conversion
function convertSize(size) {
	var sizes = ["Bytes", "KB", "MB", "GB", "TB"];

	if ( size == 0 ){

		return "0 Byte";
	}
	var i = parseInt(Math.floor(Math.log(size) / Math.log(1024)));

	return Math.round(size / Math.pow(1024, i), 2) + " " + sizes[i];
}

function convert_unixdatetime_bytes() {

	$(".js-convert-unix-timestamp").each(function(e) {
		let value = $(this).attr("data-timestamp");
		let human_dt = moment.unix(value).format("MM/DD/YYYY");

		$(this).html(human_dt);
	})
	$(".js-convert-bytes-size").each(function(e) {
		let value = $(this).attr("data-bytes");
		let human_dt = convertSize(value);

		$(this).html(human_dt);
	})
}


/***
 *    888                             
 *    888                             
 *    888                             
 *    888888 888d888 .d88b.   .d88b.  
 *    888    888P"  d8P  Y8b d8P  Y8b 
 *    888    888    88888888 88888888 
 *    Y88b.  888    Y8b.     Y8b.     
 *     "Y888 888     "Y8888   "Y8888  
 *                                    
 *                                    
 *                                    
 */

function breadcrumbs_folders(source, target_id) {
	// var source = JSON.parse(JSON.stringify(source));
	let res = [];

	res.push(...source);
	for (let i = 0; i < res.length; i++) {
		let curData = res[i];

		if ( curData.id == target_id ) {
			var result = [];

			return (
				function findParent(data) {
					result.unshift({
						id: data.id,
						name: data.name,
					});
					if (data.parent) {

						return findParent(data.parent);
					}

					return result;
				}
			)(curData);
		}
		if ( curData.children ) {
			res.push(...curData.children.map(dta => {
				dta.parent = curData;

				return dta;
			}));
		}
	}

	return [];
}
// Convert flat list to nested array
function convert_array_to_nested(list) {
	var map = {},
		node,
		roots = [],
		len,
		i;

	for (i = 0, len = list.length; i < len; i++) {
		map[list[i].id] = i; // initialize the map
		list[i].children = []; // initialize the children
	}
	for (i = 0, len = list.length; i < len; i++) {
		node = list[i];
		if ( node.parent_id != 0 ) {
			// if you have dangling branches check that map[node.parentId] exists
			list[map[node.parent_id]].children.push(node);
		} else {
			roots.push(node);
		}
	}

	return roots;
}

function getChildren(id, arrayData) {
	var children = [];
	var notMatching = [];

	_.filter(arrayData, function(c) {
		if ( c["parent_id"] == id ) {

			return true;
		} else {
			notMatching.push(c);
		}
	}).forEach(function(c) {
		children.push(c);
		children = children.concat(getChildren(c.id, notMatching));
	})

	return children;
}

// https://stackoverflow.com/questions/36702379/javascript-flatten-deep-nested-children
function getChildren_alt(id, categories) {
	var children = [];

	_.filter(categories, function(c) {

		return c["parent_id"] === id;
	}).forEach(function(c) {
		children.push(c);
		children = children.concat(getChildren(c.id, categories));
	})

	return children;
}



/***
 *    888               888                           
 *    888               888                           
 *    888               888                           
 *    88888b.   .d88b.  888 88888b.   .d88b.  888d888 
 *    888 "88b d8P  Y8b 888 888 "88b d8P  Y8b 888P"   
 *    888  888 88888888 888 888  888 88888888 888     
 *    888  888 Y8b.     888 888 d88P Y8b.     888     
 *    888  888  "Y8888  888 88888P"   "Y8888  888     
 *                          888                       
 *                          888                       
 *                          888                       
 */



function array_pluck(array, key) {

	return array.map(function(obj) {

		return obj[key];
	});
}




init();


}());