TYPE=TRIGGERS
triggers='CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_website_after_insert AFTER INSERT ON catalog_product_website FOR EACH ROW\nBEGIN\nINSERT IGNORE INTO `scconnector_google_remove_cl` (`entity_id`) VALUES (NEW.`product_id`);\nEND' 'CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_website_after_update AFTER UPDATE ON catalog_product_website FOR EACH ROW\nBEGIN\nIF (NOT(NEW.`product_id` <=> OLD.`product_id`) OR NOT(NEW.`website_id` <=> OLD.`website_id`)) THEN INSERT IGNORE INTO `scconnector_google_remove_cl` (`entity_id`) VALUES (NEW.`product_id`); END IF;\nEND' 'CREATE DEFINER=`magento`@`%` TRIGGER trg_catalog_product_website_after_delete AFTER DELETE ON catalog_product_website FOR EACH ROW\nBEGIN\nINSERT IGNORE INTO `scconnector_google_remove_cl` (`entity_id`) VALUES (OLD.`product_id`);\nEND'
sql_modes=0 0 0
definers='magento@%' 'magento@%' 'magento@%'
client_cs_names='utf8' 'utf8' 'utf8'
connection_cl_names='utf8_general_ci' 'utf8_general_ci' 'utf8_general_ci'
db_cl_names='latin1_swedish_ci' 'latin1_swedish_ci' 'latin1_swedish_ci'
created=158398269432 158398269433 158398269434
