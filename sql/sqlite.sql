CREATE TABLE "files" ( 
	`id` INTEGER, 
	`folder_id` INTEGER, 
	`name` TEXT, 
	`normalized` TEXT, 
	`extension` TEXT, 
	`size` INT, 
	`type` TEXT, 
	`saved_as` TEXT, 
	`thumbnail` TEXT, 
	`lastmodified_on` INTEGER, 
	`created_on` INTEGER, 
	PRIMARY KEY(`id`), 
	FOREIGN KEY(`folder_id`) REFERENCES `folders`(`id`) ON DELETE CASCADE
);

CREATE TABLE "folders" ( 
	`id` INTEGER,
	`parent_id` INTEGER DEFAULT 0, 
	`name` TEXT,
	`path` TEXT,
	`lastmodified_on` INTEGER, 
	`normalized` TEXT, 
	`created_on` INTEGER, 
	PRIMARY KEY(`id`)
);

CREATE TABLE "tree_path" ( 
	`ancestor` INTEGER,  
	`descendant` INTEGER, 
	`depth` INTEGER DEFAULT 0, 
	FOREIGN KEY(`descendant`) REFERENCES `folders`(`id`), 
	PRIMARY KEY(`ancestor`,`descendant`), 
	FOREIGN KEY(`ancestor`) REFERENCES `folders`(`id`)
);

INSERT INTO folders (id, name, normalized, path, lastmodified_on, created_on) VALUES (1, 'Home','HOME', 'HOME/', 1551204126, 1551204126);
INSERT INTO tree_path (ancestor,descendant,depth) VALUES (1, 1, 0);

CREATE INDEX `id_path` ON `folders` ( `id`, `path` );
