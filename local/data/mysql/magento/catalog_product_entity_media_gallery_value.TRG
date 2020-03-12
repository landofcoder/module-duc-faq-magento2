TYPE=TRIGGERS
triggers='CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_entity_media_gallery_value_after_insert AFTER INSERT ON catalog_product_entity_media_gallery_value FOR EACH ROW\nBEGIN\nINSERT IGNORE INTO `scconnector_google_feed_cl` (`entity_id`) VALUES (NEW.`entity_id`);\nEND' 'CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_entity_media_gallery_value_after_update AFTER UPDATE ON catalog_product_entity_media_gallery_value FOR EACH ROW\nBEGIN\nIF (NOT(NEW.`value_id` <=> OLD.`value_id`) OR NOT(NEW.`store_id` <=> OLD.`store_id`) OR NOT(NEW.`entity_id` <=> OLD.`entity_id`) OR NOT(NEW.`label` <=> OLD.`label`) OR NOT(NEW.`position` <=> OLD.`position`) OR NOT(NEW.`disabled` <=> OLD.`disabled`) OR NOT(NEW.`record_id` <=> OLD.`record_id`)) THEN INSERT IGNORE INTO `scconnector_google_feed_cl` (`entity_id`) VALUES (NEW.`entity_id`); END IF;\nEND' 'CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_entity_media_gallery_value_after_delete AFTER DELETE ON catalog_product_entity_media_gallery_value FOR EACH ROW\nBEGIN\nINSERT IGNORE INTO `scconnector_google_feed_cl` (`entity_id`) VALUES (OLD.`entity_id`);\nEND'
sql_modes=0 0 0
definers='magento@%' 'magento@%' 'magento@%'
client_cs_names='utf8' 'utf8' 'utf8'
connection_cl_names='utf8_general_ci' 'utf8_general_ci' 'utf8_general_ci'
db_cl_names='latin1_swedish_ci' 'latin1_swedish_ci' 'latin1_swedish_ci'
created=158398269516 158398269517 158398269518
