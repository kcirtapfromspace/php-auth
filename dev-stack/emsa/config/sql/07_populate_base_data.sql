--
-- PostgreSQL database dump
--

-- Dumped from database version 9.4.9
-- Dumped by pg_dump version 9.4.0
-- Started on 2016-11-18 15:45:38

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = elr, public, pg_catalog;

--
-- TOC entry 4316 (class 0 OID 401901)
-- Dependencies: 188
-- Data for Name: auth_role_types; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO auth_role_types (id, label) VALUES (1, 'Administrator');
INSERT INTO auth_role_types (id, label) VALUES (2, 'QA');
INSERT INTO auth_role_types (id, label) VALUES (3, 'Data Entry');


--
-- TOC entry 4352 (class 0 OID 0)
-- Dependencies: 189
-- Name: auth_role_types_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('auth_role_types_id_seq', 3, true);


--
-- TOC entry 4318 (class 0 OID 401918)
-- Dependencies: 192
-- Data for Name: batch_notification_config; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO batch_notification_config (id, udoh_enable, lhd_enable, udoh_email) VALUES (1, true, true, 'example@example.com');


--
-- TOC entry 4319 (class 0 OID 402083)
-- Dependencies: 228
-- Data for Name: intake_stats_config; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO intake_stats_config (id, received_sigma, accepted_sigma, connectors, reporters, distribution_list) VALUES (1, 0.000000, 2.000000, NULL, NULL, 'example@example.com');


--
-- TOC entry 4353 (class 0 OID 0)
-- Dependencies: 229
-- Name: intake_stats_config_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('intake_stats_config_id_seq', 1, true);


--
-- TOC entry 4340 (class 0 OID 402384)
-- Dependencies: 289
-- Data for Name: system_statuses; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (1, 'White', 1, 0, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (2, 'Gray', 0, 4, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (3, 'Exception', 0, 5, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (4, 'Black', 0, 5, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (7, 'Missing Loinc Code', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (8, 'Loinc Exception', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (9, 'Continue Processing', 1, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (10, 'Missing Zip Code Exception', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (11, 'Outside Jurisdiction or Missing Zip Relationship', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (12, 'Pending - Legacy', 0, 1, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (14, 'Assigned', 0, 3, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (15, 'Lab and Event Same', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (16, 'Processing Error', 3, 0, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (17, 'Entry', 0, 0, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (19, 'QA', 0, 6, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (20, 'Graylist Pending', 0, 7, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (21, 'Graylist Processed', 0, 8, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (22, 'Graylist Unprocessable', 0, 9, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (23, 'Graylist Exception', 0, 10, 0);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (24, 'Semi-Automated Entry', 0, 2, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (25, 'Pending', 0, 1, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (26, 'Unprocessed', 0, 0, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (27, 'Locked', 0, 0, 2);
INSERT INTO system_statuses (id, name, parent_id, sort, type) VALUES (28, 'Out of State', 0, 0, 2);


--
-- TOC entry 4363 (class 0 OID 0)
-- Dependencies: 290
-- Name: system_statuses_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_statuses_id_seq', 28, true);


--
-- TOC entry 4334 (class 0 OID 402256)
-- Dependencies: 269
-- Data for Name: system_menus; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (1, 'Dashboard', 'Dashboard', NULL, 1, 1);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (4, 'Assigned', 'Assigned', NULL, 1, 4);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (3, 'Entry', 'Entry', NULL, 1, 2);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (5, 'Gray', 'Gray', NULL, 1, 5);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (6, 'Admin', 'Admin', NULL, 1, 6);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (8, 'Non-ELR Data', 'Non-ELR Data', NULL, 1, 7);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (25, 'Semi-Automated Entry', 'Semi-Automated Entry', NULL, 1, 3);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (17, 'EMSA Tab - Full Lab', 'EMSA Tab - Full Lab', NULL, 2, 8);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (18, 'EMSA Tab - Audit Log', 'EMSA Tab - Audit Log', NULL, 2, 9);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (19, 'EMSA Tab - Error Flags', 'EMSA Tab - Error Flags', NULL, 2, 10);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (20, 'EMSA Tab - HL7', 'EMSA Tab - HL7', NULL, 2, 11);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (21, 'EMSA Tab - XML', 'EMSA Tab - XML', NULL, 2, 12);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (22, 'EMSA Action Bar - Move Message', 'EMSA Action Bar - Move Message', NULL, 2, 14);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (23, 'EMSA Action Bar - Delete Message', 'EMSA Action Bar - Delete Message', NULL, 2, 15);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (24, 'EMSA Tab - QA', 'EMSA Tab - QA', NULL, 2, 13);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (2, 'Pending - Legacy', 'Pending - Legacy', NULL, 0, 3);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (26, 'Pending', 'Pending', NULL, 1, 3);
INSERT INTO system_menus (id, name, menu_name, menu_url, menu_type, sorty) VALUES (27, 'Out of State', 'Out of State', NULL, 1, 3);


--
-- TOC entry 4360 (class 0 OID 0)
-- Dependencies: 270
-- Name: system_menus_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_menus_id_seq', 27, true);


--
-- TOC entry 4326 (class 0 OID 402162)
-- Dependencies: 246
-- Data for Name: structure_lookup_operator; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_lookup_operator (id, label) VALUES (1, 'Direct Copy');
INSERT INTO structure_lookup_operator (id, label) VALUES (2, 'Code Lookup');
INSERT INTO structure_lookup_operator (id, label) VALUES (3, 'Complex');


--
-- TOC entry 4356 (class 0 OID 0)
-- Dependencies: 247
-- Name: structure_lookup_operator_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('structure_lookup_operator_id_seq', 17, true);


--
-- TOC entry 4328 (class 0 OID 402167)
-- Dependencies: 248
-- Data for Name: structure_operand_type; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_operand_type (id, label) VALUES (1, 'From value');
INSERT INTO structure_operand_type (id, label) VALUES (2, 'From path');
INSERT INTO structure_operand_type (id, label) VALUES (3, 'From now');
INSERT INTO structure_operand_type (id, label) VALUES (4, 'From path date');
INSERT INTO structure_operand_type (id, label) VALUES (5, 'From lookup');
INSERT INTO structure_operand_type (id, label) VALUES (6, 'From in');


--
-- TOC entry 4329 (class 0 OID 402170)
-- Dependencies: 249
-- Data for Name: structure_operator; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (9, 'In', '&isin;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (2, 'Not equal', '&ne;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (1, 'Equal', '=', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (3, 'Greater than', '&gt;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (4, 'Less than', '&lt;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (5, 'Greater than or equal to', '&ge;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (6, 'Less than or equal to', '&le;', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (7, 'And', '&amp;', 2);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (8, 'Or', '|', 2);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (10, 'Contains', 'Contains', 1);
INSERT INTO structure_operator (id, label, graphical, operator_type) VALUES (11, 'Does Not Contain', 'Does Not Contain', 1);


--
-- TOC entry 4321 (class 0 OID 402132)
-- Dependencies: 239
-- Data for Name: structure_category; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_category (id, label) VALUES (4, 'gender');
INSERT INTO structure_category (id, label) VALUES (6, 'ethnicity');
INSERT INTO structure_category (id, label) VALUES (11, 'age_type');
INSERT INTO structure_category (id, label) VALUES (12, 'case');
INSERT INTO structure_category (id, label) VALUES (14, 'contactdispositiontype');
INSERT INTO structure_category (id, label) VALUES (15, 'contact_type');
INSERT INTO structure_category (id, label) VALUES (16, 'county');
INSERT INTO structure_category (id, label) VALUES (17, 'eventdisposition');
INSERT INTO structure_category (id, label) VALUES (19, 'imported');
INSERT INTO structure_category (id, label) VALUES (20, 'lab_interpretation');
INSERT INTO structure_category (id, label) VALUES (21, 'lab_test_type');
INSERT INTO structure_category (id, label) VALUES (22, 'language');
INSERT INTO structure_category (id, label) VALUES (23, 'loinc_scale');
INSERT INTO structure_category (id, label) VALUES (25, 'race');
INSERT INTO structure_category (id, label) VALUES (26, 'specimen');
INSERT INTO structure_category (id, label) VALUES (27, 'state');
INSERT INTO structure_category (id, label) VALUES (28, 'task_category');
INSERT INTO structure_category (id, label) VALUES (29, 'telephonelocationtype');
INSERT INTO structure_category (id, label) VALUES (30, 'test_result');
INSERT INTO structure_category (id, label) VALUES (31, 'test_status');
INSERT INTO structure_category (id, label) VALUES (32, 'test_type');
INSERT INTO structure_category (id, label) VALUES (33, 'yesno');
INSERT INTO structure_category (id, label) VALUES (13, 'condition');
INSERT INTO structure_category (id, label) VALUES (24, 'organism');
INSERT INTO structure_category (id, label) VALUES (37, 'result_type');
INSERT INTO structure_category (id, label) VALUES (38, 'abnormal_flag');
INSERT INTO structure_category (id, label) VALUES (39, 'disease_category');
INSERT INTO structure_category (id, label) VALUES (40, 'result_value');
INSERT INTO structure_category (id, label) VALUES (41, 'snomed_category');
INSERT INTO structure_category (id, label) VALUES (42, 'facility');
INSERT INTO structure_category (id, label) VALUES (43, 'treatments');
INSERT INTO structure_category (id, label) VALUES (44, 'facility_visit_type');
INSERT INTO structure_category (id, label) VALUES (45, 'resist_test_agent');
INSERT INTO structure_category (id, label) VALUES (46, 'discharge_disposition');
INSERT INTO structure_category (id, label) VALUES (47, 'treatment_status');
INSERT INTO structure_category (id, label) VALUES (48, 'resist_test_result');


--
-- TOC entry 4355 (class 0 OID 0)
-- Dependencies: 242
-- Name: structure_category_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('structure_category_id_seq', 48, true);


--
-- TOC entry 4342 (class 0 OID 402396)
-- Dependencies: 293
-- Data for Name: vocab_app; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO vocab_app (id, app_name, enabled, trigger_notifications) VALUES (1, 'TriSano', FALSE, FALSE);
INSERT INTO vocab_app (id, app_name, enabled, trigger_notifications) VALUES (2, 'EpiTrax', TRUE, TRUE);


--
-- TOC entry 4322 (class 0 OID 402138)
-- Dependencies: 240
-- Data for Name: structure_category_application; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (5, 1, 'external_codes', 'age_type', 11);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (6, 1, 'external_codes', 'case', 12);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (8, 1, 'external_codes', 'contactdispositiontype', 14);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (9, 1, 'external_codes', 'contact_type', 15);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (10, 1, 'external_codes', 'county', 16);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (11, 1, 'external_codes', 'eventdisposition', 17);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (13, 1, 'external_codes', 'imported', 19);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (14, 1, 'external_codes', 'lab_interpretation', 20);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (15, 1, 'external_codes', 'lab_test_type', 21);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (17, 1, 'external_codes', 'loinc_scale', 23);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (19, 1, 'external_codes', 'race', 25);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (22, 1, 'external_codes', 'task_category', 28);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (23, 1, 'external_codes', 'telephonelocationtype', 29);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (27, 1, 'external_codes', 'yesno', 33);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (7, 1, 'diseases', NULL, 13);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (18, 1, 'organisms', NULL, 24);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (26, 1, 'common_test_types', '', 32);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (25, 1, 'external_codes', 'test_status', 31);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (2, 1, 'external_codes', 'gender', 4);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (3, 1, 'external_codes', 'ethnicity', 6);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (16, 1, 'external_codes', 'language', 22);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (20, 1, 'external_codes', 'specimen', 26);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (21, 1, 'external_codes', 'state', 27);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (24, 1, 'external_codes', 'test_result', 30);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (31, 1, '', '', 37);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (32, 1, '', '', 38);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (33, 1, '', '', 39);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (34, 1, '', '', 41);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (35, 1, '', '', 42);
INSERT INTO structure_category_application (id, app_id, app_table, app_category, category_id) VALUES (36, 1, 'treatments', '', 43);


--
-- TOC entry 4354 (class 0 OID 0)
-- Dependencies: 241
-- Name: structure_category_application_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('structure_category_application_id_seq', 36, true);


--
-- TOC entry 4325 (class 0 OID 402149)
-- Dependencies: 243
-- Data for Name: structure_data_type; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_data_type (id, label) VALUES (1, 'String');
INSERT INTO structure_data_type (id, label) VALUES (2, 'Date');
INSERT INTO structure_data_type (id, label) VALUES (3, 'Number');


--
-- TOC entry 4322 (class 0 OID 402176)
-- Dependencies: 251
-- Data for Name: structure_path; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (76, '/health/labs/local_test_name/text()', 1, false, 'local_test_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (221, '/health/disease/codingSystem/text()', 1, false, 'diagnostic_code_system', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (222, '/health/labs/data_type/text()', 1, false, 'obx_data_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (3, '/health/person/middle_name/text()', 1, false, 'middle_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (223, '/health/treatments/data_source/text()', 1, false, 'data_source', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (4, '/health/person/parent_name/text()', 1, false, 'parent_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (5, '/health/person/parent_relationship/text()', 1, false, 'parent_relationship', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (6, '/health/person/street/text()', 1, false, 'street', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (8, '/health/person/unit/text()', 1, false, 'unit', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (9, '/health/person/city/text()', 1, false, 'city', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (13, '/health/person/country/text()', 1, false, 'country', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (14, '/health/person/latitude/text()', 1, false, 'latitude', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (15, '/health/person/longitude/text()', 1, false, 'longitude', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (18, '/health/person/phone_type/text()', 1, false, 'phone_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (105, '/health/epidemiological/street_num/text()', 1, false, 'street_num', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (173, '/health/sourceid/id/text()', 1, false, 'id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (19, '/health/person/area_code/text()', 1, false, 'area_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (20, '/health/person/phone/text()', 1, false, 'phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (21, '/health/person/extension/text()', 1, false, 'extension', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (22, '/health/person/email/text()', 1, false, 'email', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (28, '/health/disease/name/text()', 1, false, 'name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (29, '/health/disease/onset_date/text()', 2, false, 'onset_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (30, '/health/disease/diagnosed/text()', 1, false, 'diagnosed', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (35, '/health/hospital_info/medical_record/text()', 1, false, 'medical_record', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (36, '/health/hospital_info/died/text()', 1, false, 'died', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (37, '/health/hospital_info/date_of_death/text()', 2, false, 'date_of_death', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (39, '/health/hospital_info/due_date/text()', 2, false, 'due_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (40, '/health/treatments/given/text()', 1, false, 'given', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (42, '/health/treatments/date_of_treatment/text()', 2, false, 'date_of_treatment', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (44, '/health/clinicians/last_name/text()', 1, false, 'last_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (45, '/health/clinicians/first_name/text()', 1, false, 'first_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (46, '/health/clinicians/middle_name/text()', 1, false, 'middle_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (47, '/health/clinicians/phone_type/text()', 1, false, 'phone_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (48, '/health/clinicians/area_code/text()', 1, false, 'area_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (49, '/health/clinicians/phone/text()', 1, false, 'phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (50, '/health/clinicians/extension/text()', 1, false, 'extension', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (52, '/health/diagnostic/types/text()', 1, false, 'types', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (54, '/health/diagnostic/zipcode/text()', 1, false, 'zipcode', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (59, '/health/labs/result_value/text()', 1, false, 'result_value', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (60, '/health/labs/units/text()', 1, false, 'units', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (61, '/health/labs/reference_range/text()', 1, false, 'reference_range', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (64, '/health/labs/collection_date/text()', 2, false, 'collection_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (65, '/health/labs/lab_test_date/text()', 2, false, 'lab_test_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (66, '/health/labs/state_lab/text()', 1, false, 'state_lab', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (67, '/health/labs/comment/text()', 1, false, 'comment', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (68, '/health/labs/accession_number/text()', 1, false, 'accession_number', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (70, '/health/labs/loinc_code/text()', 1, false, 'loinc_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (71, '/health/labs/scale/text()', 1, false, 'scale', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (73, '/health/labs/system_id/text()', 1, false, 'system_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (74, '/health/labs/lab_id/text()', 1, false, 'lab_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (75, '/health/labs/result_type/text()', 1, false, 'result_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (80, '/health/labs/local_reference_range/text()', 1, false, 'local_reference_range', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (81, '/health/labs/local_result_unit/text()', 1, false, 'local_result_unit', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (82, '/health/labs/state_case_status/text()', 1, false, 'state_case_status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (83, '/health/labs/manual_entry/text()', 1, false, 'manual_entry', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (84, '/health/labs/specimen_required/text()', 1, false, 'specimen_required', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (85, '/health/contact_information/last_name/text()', 1, false, 'last_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (86, '/health/contact_information/first_name/text()', 1, false, 'first_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (87, '/health/contact_information/disposition/text()', 1, false, 'disposition', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (88, '/health/contact_information/contact_type/text()', 1, false, 'contact_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (89, '/health/contact_information/phone_type/text()', 1, false, 'phone_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (90, '/health/contact_information/area_code/text()', 1, false, 'area_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (91, '/health/contact_information/phone/text()', 1, false, 'phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (92, '/health/contact_information/extension/text()', 1, false, 'extension', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (93, '/health/encounter/investigator/text()', 1, false, 'investigator', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (94, '/health/encounter/encounter_date/text()', 2, false, 'encounter_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (95, '/health/encounter/location/text()', 1, false, 'location', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (96, '/health/encounter/description/text()', 1, false, 'description', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (97, '/health/epidemiological/food_handler/text()', 1, false, 'food_handler', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (98, '/health/epidemiological/healthcare_worker/text()', 1, false, 'healthcare_worker', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (99, '/health/epidemiological/group_living/text()', 1, false, 'group_living', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (12, '/health/person/zip/text()', 1, false, 'zip', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (17, '/health/person/age/text()', 1, false, 'age', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (16, '/health/person/date_of_birth/text()', 2, true, 'Patient Date of Birth', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (10, '/health/person/state/text()', 1, false, 'state', 27);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (2, '/health/person/last_name/text()', 1, true, 'Patient Last Name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (27, '/health/person/person_id/text()', 1, false, 'Patient ID', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (43, '/health/treatments/treatment_stopped/text()', 2, false, 'treatment_stopped', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (33, '/health/hospital_info/admission/text()', 2, false, 'admission', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (53, '/health/diagnostic/street_name/text()', 1, false, 'street_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (7, '/health/person/street_name/text()', 1, false, 'street_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (11, '/health/person/county/text()', 1, false, 'county', 16);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (25, '/health/person/race/text()', 1, false, 'race', 25);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (57, '/health/labs/organism/text()', 1, false, 'organism', 24);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (58, '/health/labs/test_result/text()', 1, false, 'test_result', 30);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (62, '/health/labs/test_status/text()', 1, false, 'test_status', 31);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (63, '/health/labs/specimen_source/text()', 1, false, 'specimen_source', 26);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (23, '/health/person/gender/text()', 1, false, 'Patient Gender', 4);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (79, '/health/labs/local_specimen_source/text()', 1, false, 'local_specimen_source', 26);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (69, '/health/labs/abnormal_flag/text()', 1, false, 'abnormal_flag', 38);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (100, '/health/epidemiological/day_care_assoc/text()', 1, false, 'day_care_assoc', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (101, '/health/epidemiological/occupation/text()', 1, false, 'occupation', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (102, '/health/epidemiological/type/text()', 1, false, 'type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (103, '/health/epidemiological/name/text()', 1, false, 'name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (1, '/health/person/first_name/text()', 1, true, 'Patient First Name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (38, '/health/hospital_info/pregnant/text()', 1, false, 'pregnant', 33);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (104, '/health/epidemiological/date_of_exposure/text()', 2, false, 'date_of_exposure', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (55, '/health/labs/lab/text()', 1, false, 'lab', 42);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (51, '/health/diagnostic/name/text()', 1, false, 'name', 42);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (32, '/health/hospital_info/facility/text()', 1, false, 'facility', 42);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (41, '/health/treatments/name/text()', 1, false, 'name', 43);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (106, '/health/epidemiological/street_name/text()', 1, false, 'street_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (107, '/health/epidemiological/unit/text()', 1, false, 'unit', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (108, '/health/epidemiological/city/text()', 1, false, 'city', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (31, '/health/hospital_info/hospitalized/text()', 1, false, 'hospitalized', 44);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (109, '/health/epidemiological/state/text()', 1, false, 'state', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (110, '/health/epidemiological/county/text()', 1, false, 'county', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (111, '/health/epidemiological/zip/text()', 1, false, 'zip', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (112, '/health/epidemiological/imported/text()', 1, false, 'imported', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (113, '/health/epidemiological/risk_factors/text()', 1, false, 'risk_factors', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (114, '/health/epidemiological/risk_factor_notes/text()', 1, false, 'risk_factor_notes', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (115, '/health/epidemiological/other_data/text()', 1, false, 'other_data', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (116, '/health/epidemiological/other_data2/text()', 1, false, 'other_data2', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (118, '/health/reporting/short_name/text()', 1, false, 'short_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (119, '/health/reporting/area_code/text()', 1, false, 'area_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (120, '/health/reporting/phone/text()', 1, false, 'phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (121, '/health/reporting/extension/text()', 1, false, 'extension', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (122, '/health/reporting/types/text()', 1, false, 'types', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (125, '/health/reporting/last_name/text()', 1, false, 'last_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (126, '/health/reporting/first_name/text()', 1, false, 'first_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (127, '/health/notes/note/text()', 1, false, 'note', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (128, '/health/administrative/record_number/text()', 1, false, 'record_number', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (129, '/health/administrative/mmwr_year/text()', 1, false, 'mmwr_year', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (130, '/health/administrative/mmwr_week/text()', 1, false, 'mmwr_week', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (131, '/health/administrative/date_record_created/text()', 2, false, 'date_record_created', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (132, '/health/administrative/lhd_case_status/text()', 1, false, 'lhd_case_status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (133, '/health/administrative/state_case_status/text()', 1, false, 'state_case_status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (134, '/health/administrative/outbreak_assoc/text()', 1, false, 'outbreak_assoc', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (135, '/health/administrative/outbreak/text()', 1, false, 'outbreak', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (136, '/health/administrative/jurisdiction_of_residence/text()', 1, false, 'jurisdiction_of_residence', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (137, '/health/administrative/event_name/text()', 1, false, 'event_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (138, '/health/administrative/event_id/text()', 1, false, 'event_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (139, '/health/administrative/participation_id/text()', 1, false, 'participation_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (140, '/health/administrative/acuity/text()', 1, false, 'acuity', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (141, '/health/administrative/jurisdictionId/text()', 1, false, 'jurisdictionId', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (142, '/health/administrative/workflow_state/text()', 1, false, 'workflow_state', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (174, '/health/sourceid/external_id/text()', 1, false, 'external_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (175, '/health/sourceid/status/text()', 1, false, 'status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (176, '/health/sourceid/system_id/text()', 1, false, 'system_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (177, '/health/sourceid/original_message/text()', 1, false, 'original_message', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (178, '/health/sourceid/original_xml/text()', 1, false, 'original_xml', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (179, '/health/sourceid/final_status/text()', 1, false, 'final_status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (180, '/health/sourceid/exception_status/text()', 1, false, 'exception_status', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (181, '/health/sourceid/status_message/text()', 1, false, 'status_message', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (182, '/health/sourceid/channel/text()', 1, false, 'channel', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (183, '/health/sourceid/event_id/text()', 1, false, 'event_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (184, '/health/sourceid/participation_id/text()', 1, false, 'participation_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (185, '/health/sourceid/add_type/text()', 1, false, 'add_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (186, '/health/sourceid/entity_id/text()', 1, false, 'entity_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (187, '/health/sourceid/schema_name/text()', 1, false, 'schema_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (188, '/health/sourceid/master_xml_id/text()', 1, false, 'master_xml_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (189, '/health/sourceid/event_record_id/text()', 1, false, 'event_record_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (190, '/health/performing_lab/last_name/text()', 1, false, 'last_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (191, '/health/performing_lab/first_name/text()', 1, false, 'first_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (192, '/health/performing_lab/middle_name/text()', 1, false, 'middle_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (193, '/health/performing_lab/name/text()', 1, false, 'name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (194, '/health/performing_lab/phone/text()', 1, false, 'phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (124, '/health/reporting/report_doh_date/text()', 2, false, 'report_doh_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (195, '/health/performing_lab/address/text()', 1, false, 'address', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (196, '/health/performing_lab/city/text()', 1, false, 'city', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (197, '/health/performing_lab/state/text()', 1, false, 'state', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (198, '/health/performing_lab/zip/text()', 1, false, 'zip', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (199, '/health/exceptions/exception_id/text()', 1, false, 'exception_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (200, '/health/exceptions/message/text()', 1, false, 'message', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (123, '/health/reporting/report_date/text()', 2, true, 'report_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (34, '/health/hospital_info/discarge/text()', 2, false, 'discarge', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (203, '/health/labs/local_code/text()', 1, false, 'local_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (77, '/health/labs/local_loinc_code/text()', 1, false, 'local_loinc_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (205, '/health/diagnostic/unit_number/text()', 1, false, 'unit_number', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (206, '/health/diagnostic/city/text()', 1, false, 'city', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (24, '/health/person/ethnicity/text()', 1, false, 'ethnicity', 6);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (26, '/health/person/language/text()', 1, false, 'language', 22);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (204, '/health/diagnostic/state/text()', 1, false, 'state', 27);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (56, '/health/labs/test_type/text()', 1, false, 'test_type', 32);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (210, '/health/labs/local_code_test_name/text()', 1, false, 'local_code_test_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (212, '/health/labs/segment_index/text()', 1, false, 'segment_index', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (78, '/health/labs/local_result_value/text()', 1, false, 'local_result_value', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (211, '/health/labs/local_result_value_2/text()', 1, false, 'local_result_value_2', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (117, '/health/reporting/agency/text()', 1, false, 'agency', 42);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (213, '/health/labs/obr_id/text()', 1, false, 'obr_id', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (215, '/health/pregnancy/pregnancy_diagnosis/text()', 1, false, 'pregnancy_diagnosis', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (216, '/health/treatments/code/text()', 1, false, 'treatment_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (217, '/health/treatments/code_system/text()', 1, false, 'treatment_code_system', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (218, '/health/treatments/dose_quantity/text()', 1, false, 'dose_qty', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (220, '/health/disease/segment_index/text()', 1, false, 'disease_segment_index', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (219, '/health/disease/code/text()', 1, false, 'disease_diagnostic_code', NULL);

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (224, '/health/labs/parent_collection_date/text()', 2, FALSE, 'lp_collection_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (225, '/health/labs/parent_lab_test_date/text()', 2, FALSE, 'lp_test_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (226, '/health/labs/parent_loinc_code/text()', 1, FALSE, 'lp_loinc_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (227, '/health/labs/parent_accession_number/text()', 1, FALSE, 'lp_accession_number', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (228, '/health/labs/parent_specimen_source/text()', 1, FALSE, 'lp_specimen_source', vocab_category_id('specimen'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (229, '/health/labs/parent_abnormal_flag/text()', 1, FALSE, 'lp_abnormal_flag', vocab_category_id('abnormal_flag'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (230, '/health/labs/parent_test_status/text()', 1, FALSE, 'lp_test_status', vocab_category_id('test_status'));

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (231, '/health/hospital_info/discharge_disposition/text()', 1, FALSE, 'discharge_disposition', vocab_category_id('discharge_disposition'));

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (232, '/health/person_facilities/admission_date/text()', 2, FALSE, 'pf_admission_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (233, '/health/person_facilities/discharge_date/text()', 2, FALSE, 'pf_discharge_date', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (234, '/health/person_facilities/discharge_disposition/text()', 1, FALSE, 'pf_discharge_disposition', vocab_category_id('discharge_disposition'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (235, '/health/person_facilities/facility_visit_type/text()', 1, FALSE, 'pf_visit_type', vocab_category_id('facility_visit_type'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (236, '/health/person_facilities/medical_record_number/text()', 1, FALSE, 'pf_medical_record', NULL);

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (237, '/health/person_facilities/provider/last_name/text()', 1, FALSE, 'pf_last_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (238, '/health/person_facilities/provider/first_name/text()', 1, FALSE, 'pf_first_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (239, '/health/person_facilities/provider/middle_name/text()', 1, FALSE, 'pf_middle_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (240, '/health/person_facilities/provider/area_code/text()', 1, FALSE, 'pf_area_code', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (241, '/health/person_facilities/provider/phone/text()', 1, FALSE, 'pf_phone', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (242, '/health/person_facilities/provider/phone_type/text()', 1, FALSE, 'pf_phone_type', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (243, '/health/person_facilities/provider/extension/text()', 1, FALSE, 'pf_extension', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (244, '/health/person_facilities/provider/email/text()', 1, FALSE, 'pf_email', NULL);

INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (245, '/health/person_facilities/facility/name/text()', 1, FALSE, 'pf_name', vocab_category_id('facility'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (246, '/health/person_facilities/facility/street_name/text()', 1, FALSE, 'pf_street_name', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (247, '/health/person_facilities/facility/unit_number/text()', 1, FALSE, 'pf_unit_number', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (248, '/health/person_facilities/facility/city/text()', 1, FALSE, 'pf_city', NULL);
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (249, '/health/person_facilities/facility/state/text()', 1, FALSE, 'pf_state', vocab_category_id('state'));
INSERT INTO structure_path (id, xpath, data_type_id, required, element, category_id) VALUES (250, '/health/person_facilities/facility/zipcode/text()', 1, FALSE, 'pf_zipcode', NULL);


SELECT pg_catalog.setval('structure_path_id', 250, true);


--
-- TOC entry 4323 (class 0 OID 402184)
-- Dependencies: 252
-- Data for Name: structure_path_application; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (1028, '/nedssHealth/person/personCondition/addressAtDiagnosis/beginning/text()', false, 'addr_earliest_known', 2, 1, 123, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (1029, '/nedssHealth/person/personCondition/personTreatment/dataSource/text()', false, 'data_source', 2, 1, 223, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (811, '/nedssHealth/person/race/code/text()', false, 'race', 2, 2, 25, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (791, '/nedssHealth/person/birthGender/code/text()', false, 'birth_gender_id', 2, 2, 23, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (792, '/nedssHealth/person/ethnicity/code/text()', false, 'ethnicity_id', 2, 2, 24, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (746, '/nedssHealth/person/firstName/text()', false, 'firstName', 2, 1, 1, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (801, '/nedssHealth/person/language/code/text()', false, 'primary_language_id', 2, 2, 26, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (798, '/nedssHealth/person/personCondition/addressAtDiagnosis/county/code/text()', false, 'county_id', 2, 3, 11, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (795, '/nedssHealth/person/personCondition/addressAtDiagnosis/state/code/text()', false, 'state_id', 2, 2, 10, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (779, '/nedssHealth/person/personCondition/addressAtDiagnosis/street/text()', false, 'street_name', 2, 1, 7, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (780, '/nedssHealth/person/personCondition/addressAtDiagnosis/unitNumber/text()', false, 'unit_number', 2, 1, 8, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (816, '/nedssHealth/person/personCondition/agency/id/text()', false, 'juisdiction', 2, 3, 141, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (796, '/nedssHealth/person/personCondition/causedDeath/text()', false, 'died_id', 2, 2, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (831, '/nedssHealth/person/personCondition/child/contactType/code/text()', false, 'contact_type_id', 2, 2, 88, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (830, '/nedssHealth/person/personCondition/child/disposition/code/text()', false, 'disposition_id', 2, 2, 87, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (826, '/nedssHealth/person/personCondition/child/occupation/text()', false, 'occupation', 2, 1, 101, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (788, '/nedssHealth/person/personCondition/child/person/firstName/text()', false, 'first_name', 2, 1, 86, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (789, '/nedssHealth/person/personCondition/child/person/lastName/text()', false, 'last_name', 2, 1, 85, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (790, '/nedssHealth/person/personCondition/child/person/personTelephone/areaCode/text()', false, 'area_code', 2, 1, 90, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (822, '/nedssHealth/person/personCondition/child/person/personTelephone/extension/text()', false, 'extension', 2, 1, 92, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (821, '/nedssHealth/person/personCondition/child/person/personTelephone/phoneNumber/text()', false, 'phone_number', 2, 1, 91, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (825, '/nedssHealth/person/personCondition/child/riskFactorsNotes/text()', false, 'risk_factors_notes', 2, 1, 114, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (824, '/nedssHealth/person/personCondition/child/riskFactors/text()', false, 'risk_factors', 2, 1, 113, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (806, '/nedssHealth/person/personCondition/condition/name/text()', false, 'disease_id', 2, 3, 28, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (778, '/nedssHealth/person/personCondition/currentWorkflow/code/text()', false, 'workflow_state', 2, 1, 142, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (775, '/nedssHealth/person/personCondition/dateDiagnosed/text()', false, 'date_diagnosed', 2, 1, 30, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (774, '/nedssHealth/person/personCondition/diseaseOnsetDate/text()', false, 'disease_onset_date', 2, 1, 29, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (776, '/nedssHealth/person/personCondition/firstReportedPhDate/text()', false, 'first_reported_PH_date', 2, 1, 123, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (820, '/nedssHealth/person/personCondition/lab/accessionNo/text()', false, 'accession_no', 2, 1, 68, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (771, '/nedssHealth/person/personCondition/lab/collectionDate/text()', false, 'collection_date', 2, 1, 64, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (770, '/nedssHealth/person/personCondition/lab/labFacility/name/text()', false, 'name', 2, 2, 55, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (772, '/nedssHealth/person/personCondition/lab/labTest/labTestDate/text()', false, 'lab_test_date', 2, 1, 65, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (794, '/nedssHealth/person/personCondition/lab/labTest/labTestResult/comment/text()', false, 'comment', 2, 3, 67, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (805, '/nedssHealth/person/personCondition/lab/labTest/labTestResult/organism/code/text()', false, 'organism_id', 2, 3, 57, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (809, '/nedssHealth/person/personCondition/lab/labTest/labTestResult/resultValue/text()', false, 'result_value', 2, 3, 59, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (808, '/nedssHealth/person/personCondition/lab/labTest/labTestResult/testResult/code/text()', false, 'test_result_id', 2, 3, 58, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (797, '/nedssHealth/person/personCondition/lab/labTest/labTestResult/units/text()', false, 'units', 2, 3, 60, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (819, '/nedssHealth/person/personCondition/lab/labTest/loincCode/text()', false, 'loinc_code', 2, 1, 70, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (793, '/nedssHealth/person/personCondition/lab/labTest/referenceRange/text()', false, 'reference_range', 2, 3, 61, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (799, '/nedssHealth/person/personCondition/lab/labTest/testStatus/code/text()', false, 'test_status_id', 2, 2, 62, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (814, '/nedssHealth/person/personCondition/lab/labTest/testType/code/text()', false, 'test_type_id', 2, 3, 56, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (835, '/nedssHealth/person/personCondition/lab/orderingFacility/address/city/text()', false, 'city', 2, 1, 206, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (815, '/nedssHealth/person/personCondition/lab/orderingFacility/address/state/code/text()', false, 'state_id', 2, 2, 204, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (833, '/nedssHealth/person/personCondition/lab/orderingFacility/address/street/text()', false, 'street_name', 2, 1, 53, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (834, '/nedssHealth/person/personCondition/lab/orderingFacility/address/unitNumber/text()', false, 'unit_number', 2, 1, 205, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (817, '/nedssHealth/person/personCondition/lab/orderingFacility/address/zip/text()', false, 'postal_code', 2, 3, 54, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (832, '/nedssHealth/person/personCondition/lab/orderingFacility/name/text()', false, 'name', 2, 2, 51, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (767, '/nedssHealth/person/personCondition/lab/provider/areaCode/text()', false, 'area_code', 2, 1, 48, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (769, '/nedssHealth/person/personCondition/lab/provider/extension/text()', false, 'extension', 2, 1, 50, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (764, '/nedssHealth/person/personCondition/lab/provider/firstName/text()', false, 'first_name', 2, 1, 45, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (766, '/nedssHealth/person/personCondition/lab/provider/lastName/text()', false, 'last_name', 2, 1, 44, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (765, '/nedssHealth/person/personCondition/lab/provider/middleName/text()', false, 'middle_name', 2, 1, 46, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (768, '/nedssHealth/person/personCondition/lab/provider/phone/text()', false, 'phone_number', 2, 1, 49, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (804, '/nedssHealth/person/personCondition/lab/specimenSource/code/text()', false, 'specimen_source_id', 2, 3, 79, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (777, '/nedssHealth/person/personCondition/lhdCaseStatus/code/text()', false, 'lhd_case_status_id', 2, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (782, '/nedssHealth/person/personCondition/note/note/text()', false, 'note', 2, 1, 127, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (803, '/nedssHealth/person/personCondition/note/noteType/text()', false, 'note_type', 2, 3, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (759, '/nedssHealth/person/personCondition/occupation/text()', false, 'occupation', 2, 1, 101, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (810, '/nedssHealth/person/personCondition/otherData1/text()', false, 'other_data1', 2, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (818, '/nedssHealth/person/personCondition/otherData2/text()', false, 'other_data2', 2, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (813, '/nedssHealth/person/personCondition/parentGuardian/text()', false, 'parent_guardian', 2, 1, 4, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (800, '/nedssHealth/person/personCondition/personConditionType/code/text()', false, 'type', 2, 3, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (785, '/nedssHealth/person/personCondition/personFacility/admissionDate/text()', false, 'admission_date', 2, 1, 33, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (786, '/nedssHealth/person/personCondition/personFacility/dischargeDate/text()', false, 'discharge_date', 2, 1, 34, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (783, '/nedssHealth/person/personCondition/personFacility/facility/name/text()', false, 'name', 2, 2, 32, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (773, '/nedssHealth/person/personCondition/personFacility/facilityVisitType/code/text()', false, 'hospitalized_id', 2, 2, 31, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (784, '/nedssHealth/person/personCondition/personFacility/hospitalRecordNumber/text()', false, 'hospital_record_number', 2, 1, 128, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (787, '/nedssHealth/person/personCondition/personFacility/medicalRecordNumber/text()', false, 'medical_record_number', 2, 1, 35, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (842, '/nedssHealth/person/personCondition/personTreatment/quantity/text()', false, 'treatment_quantity', 2, 1, 218, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (763, '/nedssHealth/person/personCondition/personTreatment/stopTreatmentDate/text()', false, 'stop_treatment_date', 2, 1, 43, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (760, '/nedssHealth/person/personCondition/personTreatment/treatment/code/text()', false, 'treatment_id', 2, 2, 41, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (762, '/nedssHealth/person/personCondition/personTreatment/treatmentDate/text()', false, 'treatment_date', 2, 1, 42, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (837, '/nedssHealth/person/personCondition/reporter/firstName/text()', false, 'first_name', 2, 1, 126, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (838, '/nedssHealth/person/personCondition/reporter/lastName/text()', false, 'last_name', 2, 1, 125, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (839, '/nedssHealth/person/personCondition/reporter/phone/text()', false, 'area_code', 2, 1, 119, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (840, '/nedssHealth/person/personCondition/reporter/phone/text()', false, 'phone_number', 2, 1, 120, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (841, '/nedssHealth/person/personCondition/reporter/phone/text()', false, 'extension', 2, 1, 121, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (836, '/nedssHealth/person/personCondition/reportingFacility/name/text()', false, 'name', 2, 2, 117, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (758, '/nedssHealth/person/personCondition/riskFactorsNotes/text()', false, 'risk_factors_notes', 2, 1, 114, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (757, '/nedssHealth/person/personCondition/riskFactors/text()', false, 'risk_factors', 2, 1, 113, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (807, '/nedssHealth/person/personCondition/stateCaseStatus/code/text()', false, 'state_case_status_id', 2, 3, 82, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (761, '/nedssHealth/person/personCondition/treatmentGiven/text()', false, 'treatment_given_yn_id', 2, 2, 40, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (755, '/nedssHealth/person/personPregnantElr/pregnant/text()', false, 'pregnant_id', 2, 1, 38, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (756, '/nedssHealth/person/personPregnant/pregnancyDueDate/text()', false, 'pregnancy_due_date', 2, 1, 39, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (752, '/nedssHealth/person/personTelephone/areaCode/text()', false, 'area_code', 2, 1, 19, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (754, '/nedssHealth/person/personTelephone/extension/text()', false, 'extension', 2, 1, 21, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (753, '/nedssHealth/person/personTelephone/phoneNumber/text()', false, 'phone_number', 2, 1, 20, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (601, '/trisano_health/events/workflow_state/text()', false, 'workflow_state', 1, 1, 142, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (604, '/trisano_health/addresses/street_name/text()', false, 'street_name', 1, 1, 7, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (605, '/trisano_health/addresses/unit_number/text()', false, 'unit_number', 1, 1, 8, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (607, '/trisano_health/addresses/city/text()', false, 'city', 1, 1, 9, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (608, '/trisano_health/notes/note/text()', false, 'note', 1, 1, 127, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (552, '/trisano_health/interested_party_attributes/person/birth_gender_id/text()', false, 'birth_gender_id', 1, 2, 23, 2);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (595, '/trisano_health/disease_events/date_diagnosed/text()', false, 'date_diagnosed', 1, 1, 30, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (749, '/nedssHealth/person/birthDate/text()', false, 'birth_date', 2, 1, 16, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (750, '/nedssHealth/person/dateOfDeath/text()', false, 'date_of_death', 2, 1, 37, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (751, '/nedssHealth/person/daysOldNoBirthday/text()', false, 'approximate_age_no_birthday', 2, 1, 17, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (748, '/nedssHealth/person/lastName/text()', false, 'last_name', 2, 1, 2, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (747, '/nedssHealth/person/middleName/text()', false, 'middleName', 2, 1, 3, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (781, '/nedssHealth/person/personCondition/addressAtDiagnosis/city/text()', false, 'city', 2, 1, 9, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (802, '/nedssHealth/person/personCondition/addressAtDiagnosis/postalCode/text()', false, 'postal_code', 2, 3, 12, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (555, '/trisano_health/interested_party_attributes/person/first_name/text()', false, 'first_name', 1, 1, 1, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (556, '/trisano_health/interested_party_attributes/person/middle_name/text()', false, 'middle_name', 1, 1, 3, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (557, '/trisano_health/interested_party_attributes/person/last_name/text()', false, 'last_name', 1, 1, 2, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (558, '/trisano_health/interested_party_attributes/person/birth_date/text()', false, 'birth_date', 1, 1, 16, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (559, '/trisano_health/interested_party_attributes/person/date_of_death/text()', false, 'date_of_death', 1, 1, 37, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (560, '/trisano_health/interested_party_attributes/person/approximate_age_no_birthday/text()', false, 'approximate_age_no_birthday', 1, 1, 17, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (561, '/trisano_health/interested_party_attributes/telephones/area_code/text()', false, 'area_code', 1, 1, 19, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (562, '/trisano_health/interested_party_attributes/telephones/phone_number/text()', false, 'phone_number', 1, 1, 20, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (563, '/trisano_health/interested_party_attributes/telephones/extension/text()', false, 'extension', 1, 1, 21, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (565, '/trisano_health/interested_party_attributes/risk_factor_attributes/pregnancy_due_date/text()', false, 'pregnancy_due_date', 1, 1, 39, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (566, '/trisano_health/interested_party_attributes/risk_factor_attributes/risk_factors/text()', false, 'risk_factors', 1, 1, 113, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (567, '/trisano_health/interested_party_attributes/risk_factor_attributes/risk_factors_notes/text()', false, 'risk_factors_notes', 1, 1, 114, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (568, '/trisano_health/interested_party_attributes/risk_factor_attributes/occupation/text()', false, 'occupation', 1, 1, 101, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (569, '/trisano_health/interested_party_attributes/treatments_attributes/treatment_id/text()', false, 'treatment_id', 1, 2, 41, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (570, '/trisano_health/interested_party_attributes/treatments_attributes/treatment_given_yn_id/text()', false, 'treatment_given_yn_id', 1, 2, 40, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (571, '/trisano_health/interested_party_attributes/treatments_attributes/treatment_date/text()', false, 'treatment_date', 1, 1, 42, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (572, '/trisano_health/interested_party_attributes/treatments_attributes/stop_treatment_date/text()', false, 'stop_treatment_date', 1, 1, 43, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (573, '/trisano_health/clinician_attributes/clinician/first_name/text()', false, 'first_name', 1, 1, 45, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (574, '/trisano_health/clinician_attributes/clinician/middle_name/text()', false, 'middle_name', 1, 1, 46, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (575, '/trisano_health/clinician_attributes/clinician/last_name/text()', false, 'last_name', 1, 1, 44, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (576, '/trisano_health/clinician_attributes/telephones/area_code/text()', false, 'area_code', 1, 1, 48, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (577, '/trisano_health/clinician_attributes/telephones/phone_number/text()', false, 'phone_number', 1, 1, 49, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (578, '/trisano_health/clinician_attributes/telephones/extension/text()', false, 'extension', 1, 1, 50, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (581, '/trisano_health/labs_attributes/lab_results/collection_date/text()', false, 'collection_date', 1, 1, 64, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (582, '/trisano_health/labs_attributes/lab_results/lab_test_date/text()', false, 'lab_test_date', 1, 1, 65, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (594, '/trisano_health/disease_events/disease_onset_date/text()', false, 'disease_onset_date', 1, 1, 29, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (597, '/trisano_health/events/first_reported_PH_date/text()', false, 'first_reported_PH_date', 1, 1, 123, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (598, '/trisano_health/events/lhd_case_status_id/text()', false, 'lhd_case_status_id', 1, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (611, '/trisano_health/hospitalization_facilities_attributes/hospitals_participations/hospital_record_number/text()', false, 'hospital_record_number', 1, 1, 128, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (613, '/trisano_health/hospitalization_facilities_attributes/hospitals_participations/discharge_date/text()', false, 'discharge_date', 1, 1, 34, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (614, '/trisano_health/hospitalization_facilities_attributes/hospitals_participations/medical_record_number/text()', false, 'medical_record_number', 1, 1, 35, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (615, '/trisano_health/contact_child_events_attributes/interested_party_attributes/person/first_name/text()', false, 'first_name', 1, 1, 86, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (616, '/trisano_health/contact_child_events_attributes/interested_party_attributes/person/last_name/text()', false, 'last_name', 1, 1, 85, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (617, '/trisano_health/contact_child_events_attributes/interested_party_attributes/telephones/area_code/text()', false, 'area_code', 1, 1, 90, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (553, '/trisano_health/interested_party_attributes/person/ethnicity_id/text()', false, 'ethnicity_id', 1, 2, 24, 3);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (583, '/trisano_health/labs_attributes/lab_results/reference_range/text()', false, 'reference_range', 1, 3, 61, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (589, '/trisano_health/labs_attributes/lab_results/comment/text()', false, 'comment', 1, 3, 67, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (603, '/trisano_health/addresses/state_id/text()', false, 'state_id', 1, 2, 10, 21);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (593, '/trisano_health/disease_events/died_id/text()', false, 'died_id', 1, 2, NULL, 27);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (587, '/trisano_health/labs_attributes/lab_results/units/text()', false, 'units', 1, 3, 60, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (602, '/trisano_health/addresses/county_id/text()', false, 'county_id', 1, 2, 11, 10);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (588, '/trisano_health/labs_attributes/lab_results/test_status_id/text()', false, 'test_status_id', 1, 2, 62, 25);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (599, '/trisano_health/events/type/text()', false, 'type', 1, 3, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (554, '/trisano_health/interested_party_attributes/person/primary_language_id/text()', false, 'primary_language_id', 1, 2, 26, 16);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (606, '/trisano_health/addresses/postal_code/text()', false, 'postal_code', 1, 3, 12, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (609, '/trisano_health/notes/note_type/text()', false, 'note_type', 1, 3, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (580, '/trisano_health/labs_attributes/lab_results/specimen_source_id/text()', false, 'specimen_source_id', 1, 3, 79, 20);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (590, '/trisano_health/labs_attributes/lab_results/organism_id/text()', false, 'organism_id', 1, 3, 57, 18);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (591, '/trisano_health/disease_events/disease_id/text()', false, 'disease_id', 1, 3, 28, 7);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (596, '/trisano_health/events/state_case_status_id/text()', false, 'state_case_status_id', 1, 3, 82, 6);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (585, '/trisano_health/labs_attributes/lab_results/test_result_id/text()', false, 'test_result_id', 1, 3, 58, 24);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (586, '/trisano_health/labs_attributes/lab_results/result_value/text()', false, 'result_value', 1, 3, 59, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (644, '/trisano_health/events/other_data_1/text()', false, 'other_data1', 1, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (646, '/trisano_health/interested_party_attributes/people_races/serial_version_uid/race_id/text()', false, 'race', 1, 2, 25, 19);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (648, '/trisano_health/labs_attributes/lab_results/abnormal_flag/text()', false, 'abnormal_flag', 1, 2, 69, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (600, '/trisano_health/events/parent_guardian/text()', false, 'parent_guardian', 1, 1, 4, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (584, '/trisano_health/labs_attributes/lab_results/test_type_id/text()', true, 'test_type_id', 1, 3, 56, 26);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (630, '/trisano_health/diagnostic_facilities_attributes/addresses/state_id/text()', false, 'state_id', 1, 2, 204, 21);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (642, '/trisano_health/jurisdiction_attributes/place/id/text()', false, 'juisdiction', 1, 3, 141, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (633, '/trisano_health/diagnostic_facilities_attributes/addresses/postal_code/text()', false, 'postal_code', 1, 3, 54, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (645, '/trisano_health/events/other_data_2/text()', false, 'other_data2', 1, 1, NULL, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (647, '/trisano_health/labs_attributes/lab_results/loinc_code/text()', false, 'loinc_code', 1, 1, 70, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (649, '/trisano_health/labs_attributes/lab_results/accession_no/text()', false, 'accession_no', 1, 1, 68, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (618, '/trisano_health/contact_child_events_attributes/interested_party_attributes/telephones/phone_number/text()', false, 'phone_number', 1, 1, 91, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (619, '/trisano_health/contact_child_events_attributes/interested_party_attributes/telephones/extension/text()', false, 'extension', 1, 1, 92, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (620, '/trisano_health/contact_child_events_attributes/interested_party_attributes/risk_factor_attributes/pregnancy_due_date/text()', false, 'pregnancy_due_date', 1, 1, 39, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (621, '/trisano_health/contact_child_events_attributes/interested_party_attributes/risk_factor_attributes/risk_factors/text()', false, 'risk_factors', 1, 1, 113, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (622, '/trisano_health/contact_child_events_attributes/interested_party_attributes/risk_factor_attributes/risk_factors_notes/text()', false, 'risk_factors_notes', 1, 1, 114, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (623, '/trisano_health/contact_child_events_attributes/interested_party_attributes/risk_factor_attributes/occupation/text()', false, 'occupation', 1, 1, 101, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (627, '/trisano_health/contact_child_events_attributes/participations_contacts/disposition_id/text()', false, 'disposition_id', 1, 2, 87, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (628, '/trisano_health/contact_child_events_attributes/participations_contacts/contact_type_id/text()', false, 'contact_type_id', 1, 2, 88, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (631, '/trisano_health/diagnostic_facilities_attributes/addresses/street_name/text()', false, 'street_name', 1, 1, 53, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (632, '/trisano_health/diagnostic_facilities_attributes/addresses/unit_number/text()', false, 'unit_number', 1, 1, 205, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (634, '/trisano_health/diagnostic_facilities_attributes/addresses/city/text()', false, 'city', 1, 1, 206, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (636, '/trisano_health/reporter_attributes/reporter/first_name/text()', false, 'first_name', 1, 1, 126, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (637, '/trisano_health/reporter_attributes/reporter/last_name/text()', false, 'last_name', 1, 1, 125, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (638, '/trisano_health/reporter_attributes/telephones/area_code/text()', false, 'area_code', 1, 1, 119, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (639, '/trisano_health/reporter_attributes/telephones/phone_number/text()', false, 'phone_number', 1, 1, 120, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (640, '/trisano_health/reporter_attributes/telephones/extension/text()', false, 'extension', 1, 1, 121, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (564, '/trisano_health/interested_party_attributes/risk_factor_attributes/pregnant_id/text()', false, 'pregnant_id', 1, 2, 38, 27);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (592, '/trisano_health/disease_events/hospitalized_id/text()', false, 'hospitalized_id', 1, 2, 31, 27);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (635, '/trisano_health/reporting_agency_attributes/place/name/text()', false, 'name', 1, 2, 117, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (579, '/trisano_health/labs_attributes/place/name/text()', false, 'name', 1, 2, 55, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (629, '/trisano_health/diagnostic_facilities_attributes/place/name/text()', false, 'name', 1, 2, 51, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id) VALUES (610, '/trisano_health/hospitalization_facilities_attributes/place/name/text()', false, 'name', 1, 2, 32, NULL);

INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1030, '/nedssHealth/person/personEmail/emailAddress/text()', FALSE, 'email_address', 2, 1, 22, NULL);

INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1031, '/nedssHealth/person/personCondition/personFacility/admissionDate/text()', FALSE, 'pf_admission_date', 2, 1, 232, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1032, '/nedssHealth/person/personCondition/personFacility/dischargeDate/text()', FALSE, 'pf_discharge_date', 2, 1, 233, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1033, '/nedssHealth/person/personCondition/personFacility/facilityVisitType/code/text()', FALSE, 'pf_visit_type', 2, 2, 235, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1034, '/nedssHealth/person/personCondition/personFacility/medicalRecordNumber/text()', FALSE, 'pf_medical_record_number', 2, 1, 236, NULL);

INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1035, '/nedssHealth/person/personCondition/personFacility/provider/lastName/text()', FALSE, 'pf_last_name', 2, 1, 237, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1036, '/nedssHealth/person/personCondition/personFacility/provider/firstName/text()', FALSE, 'pf_first_name', 2, 1, 238, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1037, '/nedssHealth/person/personCondition/personFacility/provider/middleName/text()', FALSE, 'pf_middle_name', 2, 1, 239, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1038, '/nedssHealth/person/personCondition/personFacility/provider/areaCode/text()', FALSE, 'pf_area_code', 2, 1, 240, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1039, '/nedssHealth/person/personCondition/personFacility/provider/phone/text()', FALSE, 'pf_phone_number', 2, 1, 241, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1040, '/nedssHealth/person/personCondition/personFacility/provider/extension/text()', FALSE, 'pf_extension', 2, 1, 243, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1041, '/nedssHealth/person/personCondition/personFacility/provider/email/text()', FALSE, 'pf_email', 2, 1, 244, NULL);

INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1042, '/nedssHealth/person/personCondition/personFacility/facility/name/text()', FALSE, 'pf_facility_name', 2, 2, 245, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1043, '/nedssHealth/person/personCondition/personFacility/facility/address/street/text()', FALSE, 'pf_street_name', 2, 1, 246, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1044, '/nedssHealth/person/personCondition/personFacility/facility/address/unitNumber/text()', FALSE, 'pf_unit_number', 2, 1, 247, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1045, '/nedssHealth/person/personCondition/personFacility/facility/address/city/text()', FALSE, 'pf_city', 2, 1, 248, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1046, '/nedssHealth/person/personCondition/personFacility/facility/address/state/code/text()', FALSE, 'pf_state', 2, 2, 249, NULL);
INSERT INTO structure_path_application (id, xpath, required, element, app_id, structure_lookup_operator_id, structure_path_id, category_application_id)
  VALUES (1047, '/nedssHealth/person/personCondition/personFacility/facility/address/zip/text()', FALSE, 'pf_zipcode', 2, 3, 250, NULL);


--
-- TOC entry 4330 (class 0 OID 0)
-- Dependencies: 253
-- Name: structure_path_application_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('structure_path_application_id_seq', 1047, true);


-- Back-populate structure_path_application Complex rule handlers
UPDATE structure_path_application SET complex_rule_callback = 'ReferenceRangeRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/referenceRange/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'LabResultsCommentRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/labTestResult/comment/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'LabResultUnitRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/labTestResult/units/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'CountyIdRule' WHERE xpath = '/nedssHealth/person/personCondition/addressAtDiagnosis/county/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'EventTypeRule' WHERE xpath = '/nedssHealth/person/personCondition/personConditionType/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'PostalCodeRule' WHERE xpath = '/nedssHealth/person/personCondition/addressAtDiagnosis/postalCode/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'NoteTypeRule' WHERE xpath = '/nedssHealth/person/personCondition/note/noteType/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'SpecimenSourceIdRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/specimenSource/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'OrganismIdRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/labTestResult/organism/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'DiseaseIdRule' WHERE xpath = '/nedssHealth/person/personCondition/condition/name/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'StateCaseStatusIdRule' WHERE xpath = '/nedssHealth/person/personCondition/stateCaseStatus/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'TestResultIdRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/labTestResult/testResult/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'ResultValueRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/labTestResult/resultValue/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'TestTypeIdRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/labTest/testType/code/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'JurisdictionIdRule' WHERE xpath = '/nedssHealth/person/personCondition/agency/id/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'PostalCodeRule' WHERE xpath = '/nedssHealth/person/personCondition/lab/orderingFacility/address/zip/text()' AND app_id = 2;
UPDATE structure_path_application SET complex_rule_callback = 'PostalCodeRule' WHERE xpath = '/nedssHealth/person/personCondition/personFacility/facility/address/zip/text()' AND app_id = 2;


--
-- TOC entry 4325 (class 0 OID 402216)
-- Dependencies: 259
-- Data for Name: structure_path_rule; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (26, 64, 4, 3, '', 1, NULL);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (31, 16, 4, 3, '', 2, 7);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (32, 16, 3, 3, '-110,1', 1, 7);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (24, 65, 6, 4, '/health/reporting/report_date/text()', 1, NULL);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (25, 65, 6, 4, '/health/reporting/report_date/text()', 1, NULL);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (27, 64, 4, 4, '/health/reporting/report_date/text()', 2, 7);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (28, 64, 6, 4, '/health/labs/lab_test_date/text()', 2, 7);
INSERT INTO structure_path_rule (id, path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (29, 29, 6, 4, '/health/labs/collection_date/text()', 1, NULL);


SELECT pg_catalog.setval('structure_path_rule_id', 32, true);


--
-- TOC entry 4960 (class 0 OID 2520271)
-- Dependencies: 685
-- Data for Name: structure_hl7_valuetype_defaults; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (1, 'ST', false);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (2, 'TX', false);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (3, 'NM', true);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (4, 'SN', true);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (5, 'CE', false);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (6, 'CWE', false);
INSERT INTO structure_hl7_valuetype_defaults (id, value_type, default_interpret_results) VALUES (7, 'FT', false);


--
-- TOC entry 4965 (class 0 OID 0)
-- Dependencies: 684
-- Name: structure_hl7_valuetype_defaults_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('structure_hl7_valuetype_defaults_id_seq', 7, true);


--
-- TOC entry 4330 (class 0 OID 402224)
-- Dependencies: 260
-- Data for Name: system_action_categories; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_action_categories (id, name) VALUES (1, 'Exceptions');
INSERT INTO system_action_categories (id, name) VALUES (2, 'Lists');
INSERT INTO system_action_categories (id, name) VALUES (3, 'Edits');
INSERT INTO system_action_categories (id, name) VALUES (4, 'Retrys');
INSERT INTO system_action_categories (id, name) VALUES (5, 'Events');
INSERT INTO system_action_categories (id, name) VALUES (6, 'Cases');
INSERT INTO system_action_categories (id, name) VALUES (7, 'Duplicate');
INSERT INTO system_action_categories (id, name) VALUES (8, 'Deleted');
INSERT INTO system_action_categories (id, name) VALUES (9, 'Master Process');


--
-- TOC entry 4357 (class 0 OID 0)
-- Dependencies: 261
-- Name: system_action_categories_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_action_categories_id_seq', 9, true);


--
-- TOC entry 4345 (class 0 OID 22185999)
-- Dependencies: 506
-- Data for Name: system_alert_types; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_alert_types (id, name, type) VALUES (1, 'Unrecognized Sending Facility ID', NULL);


--
-- TOC entry 4358 (class 0 OID 0)
-- Dependencies: 505
-- Name: system_alert_types_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_alert_types_id_seq', 1, true);


--
-- TOC entry 4332 (class 0 OID 402245)
-- Dependencies: 266
-- Data for Name: system_exceptions; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (51, 51, 'Error Finding App Code With Local LOINC', 'Error Finding App Code With Local LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (3, 3, 'Zip Code', 'Zip Code not Added to ELR Manager', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (23, 23, 'Result Value Not Mapped', 'Local Result Value Not Mapped For Master/ Local Code', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (2, 2, 'LOINC Code Missing', 'LOINC Code not found in the Message', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (49, 49, 'Unable To Find Application Coded Value', 'Unable To Find Application Coded Value', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (52, 52, 'No App Code Found With Local LOINC', 'No App Code Found With Local LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (5, 5, 'Message Criteria Not Met', 'Message does not meet any criteria', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (4, 4, 'SQL Agent Error', 'SQL Agent Error', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (8, 8, 'Lab Missing Specimen', 'Lab Missing Specimen', 12, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (50, 50, 'Unable To Convert Application Doc To XML', 'Unable To Convert Application Doc To XML', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (7, 7, 'Lab Event Same', 'Lab and Event are the Same', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (13, 13, 'LOINC Values Missing', 'LOINC Code Values Missing', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (9, 9, 'Lab Missing Disease', 'Lab Missing Disease', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (10, 10, 'Lab Missing Organism', 'Lab Missing Organism', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (17, 17, 'Could Not Map System', 'Message could not be transformed and mapped to target application', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (12, 12, 'Lab Missing Test Type', 'Lab Missing Test Type', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (21, 21, 'No Loinc Result Rules Evaluated True', 'Rules were found but no LOINC Result Rules evaluated to true', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry, allow_child_vocab_add) VALUES (1, 1, 'LOINC Code', 'LOINC Code not added to ELR Manager', 3, true, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (53, 53, 'Target application ID lookup failed', 'Target application ID lookup failed', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (14, 14, 'Test Result Value', 'No Test Result found for coded result', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (15, 15, 'No Lab Not Imported', 'Message Not A Lab Or An Imported Lab', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (16, 16, 'No Health Object Created', 'No Health Object Created', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (47, 47, 'Error Accessing Path In Application XML Document', 'Error Accessing Path In Application XML Document', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (18, 18, 'Import SQL Response', 'Bad Response From SQL Agent on CMR Import', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (48, 48, 'Error Setting Value In Application XML Document', 'Error Setting Value In Application XML Document', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (19, 19, 'Do Rules SQL Error', 'Bad Response from SQL Agent On Do Rules', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (20, 20, 'Could Not Create System Object', 'Could Not Create System Object', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (22, 22, 'Failed to Add or Update', 'Failed to Add Or Update Event SQL Error', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (57, 57, 'Unable to Look Up Disease Name', 'Unable to Look Up Disease Name', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (24, 24, 'Bad Birth Date', 'Birthday is Invalid', 12, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (29, 29, 'Specimen Required But Not Found', 'Specimen Required But Not Found', 12, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (31, 31, 'Missing Required Field', 'Missing Required Field', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (32, 32, 'Invalid Value For Data Type', 'Invalid Value For Data Type', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (33, 33, 'Validation Rule Failure', 'Validation Rule Failure', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (34, 34, 'Mirth To Master Mappings Not Found', 'Mirth To Master Mappings Not Found', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (35, 35, 'Master To Application Mappings Not Found', 'Master To Application Mappings Not Found', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (36, 36, 'Missing Mirth Param Original Message Id', 'Missing Mirth Param Original Message Id', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (37, 37, 'Missing Mirth Param HL7XML', 'Missing Mirth Param HL7XML', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (38, 38, 'Missing Mirth Param Lab Name', 'Missing Mirth Param Lab Name', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (39, 39, 'Missing Mirth Param Version', 'Missing Mirth Param Version', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (40, 40, 'Lab Id Not Found For Lab Name', 'Lab Id Not Found For Lab Name', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (41, 41, 'Original Message Id Not A Number', 'Original Message Id Not A Number', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (42, 42, 'Unable To Transform Mirth XML to Master Doc', 'Unable To Transform Mirth XML to Master Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (43, 43, 'Unable To Convert Master Doc to XML', 'Unable To Convert Master Doc to XML', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (44, 44, 'Unable to Obtain Empty Application XML Document', 'Unable to Obtain Empty Application XML Document', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (45, 45, 'Unable to Add System Message', 'Unable to Add System Message', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (46, 46, 'Error Accessing Path In Master Doc', 'Error Accessing Path In Master Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (54, 54, 'Unable to Obtain Date Message Received', 'Unable to Obtain Date Message Received', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (55, 55, 'Unable to Set Date Message Received In Doc', 'Unable to Set Date Message Received In Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (56, 56, 'Unable to Evaluate Rule', 'Unable to Evaluate Rule', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (58, 58, 'Unable to Look Up Organism', 'Unable to Look Up Organism', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (59, 59, 'Unable to Set Date in Master Doc', 'Unable to Set Date in Master Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (61, 61, 'No Reference Range Found In Master Doc', 'No Reference Range Found In Master Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (67, 67, 'Specimen not mapped', 'Specimen not mapped', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (60, 60, 'Unable to Set Master LOINC in Master Doc', 'Unable to Set Master LOINC in Master Doc', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (62, 62, 'Application coded value ID needed but not found', 'Application coded value ID needed but not found', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (63, 63, 'No Test Type Found For Master LOINC', 'No Test Type Found For Master LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (64, 64, 'No Disease Name Found For Local LOINC', 'No Disease Name Found For Local LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (65, 65, 'No Test Result Rules Found For Child LOINC', 'No Test Result Rules Found For Child LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (71, 71, 'Unexpected Value In Local Result Value', 'Unexpected value in local result value', 3, false);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (74, 74, 'No Master Vocab Id Found For Local Result Value', 'No Master Vocab Id Found For Local Result Value', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (72, 72, 'No Case Management Rules Found For Master LOINC', 'No Case Management Rules Found For Master LOINC', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (73, 73, 'No Case Management Rules Evaluated True', 'No Case Management Rules Evaluated True', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (66, 66, 'Unable to assign message to target application', 'Unable to assign message to target application', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (75, 75, 'No Case Management Rules Found For Master SNOMED', 'No Case Management Rules Found For Master SNOMED', 3, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (68, 68, 'Suspect patient Last Name changed', 'Suspect patient Last Name changed', 25, true);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (69, 69, 'Entry Queue Exception', 'Entry Queue Exception', 25, false);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (70, 70, 'Whitelist Rule Exception', 'Whitelist Rule Exception', 25, false);
INSERT INTO system_exceptions (id, exception_id, name, description, exception_type_id, allow_retry) VALUES (76, 76, 'No Case Management Rules Found For Diagnostic Code', 'No Case Management Rules Found For Diagnostic Code', 3, true);


--
-- TOC entry 4359 (class 0 OID 0)
-- Dependencies: 267
-- Name: system_exceptions_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_exceptions_id_seq', 76, true);


--
-- TOC entry 4336 (class 0 OID 402265)
-- Dependencies: 271
-- Data for Name: system_message_actions; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (7, 7, 'Message contains duplicate lab results', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (8, 8, 'Message Deleted', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (9, 9, 'White', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (10, 2, 'Black', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (11, 2, 'Gray', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (12, 2, 'Pending', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (13, 2, 'Exception', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (14, 2, 'Holding', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (16, 3, 'Address Update', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (17, 3, 'Loinc Code Update', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (18, 3, 'Birth Date Update', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (19, 3, 'Last Name Update', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (20, 3, 'First Name Update', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (21, 4, 'Message Retried', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (22, 4, 'Message appended new Lab to existing CMR', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (23, 4, 'Message generated new Person and CMR event', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (24, 4, 'Message generated new CMR event for existing Person', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (25, 2, 'Message moved by user', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (26, 3, 'Message fixed from Tasks Manager', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (27, 2, 'Message moved by automated rules', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (28, 4, 'Message updated existing Lab for existing CMR', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (29, 4, 'Message appended new Lab Results to existing Lab', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (30, 4, 'Invalid Params From Mirth', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (31, 9, 'XML mapping and translation', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (32, 3, 'Message Flag Set', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (33, 3, 'Message Flag Cleared', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (34, 4, 'Automated Message Processor', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (35, 4, 'Attempted to create new Morbidity Event', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (36, 2, 'Message copy created', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (37, 2, 'Graylist Request status set/changed', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (38, 4, 'Message processed by Graylist', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (39, 4, 'Message updated event with non-laboratory data', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (40, 4, 'Attempted to update existing event', 1);
INSERT INTO system_message_actions (id, action_category_id, message, status) VALUES (41, 4, 'Record lock encountered in target system', 1);


--
-- TOC entry 4361 (class 0 OID 0)
-- Dependencies: 272
-- Name: system_message_actions_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_message_actions_id_seq', 41, true);


--
-- TOC entry 4338 (class 0 OID 402296)
-- Dependencies: 279
-- Data for Name: system_message_flags; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO system_message_flags (id, label) VALUES (1, 'Investigation Complete');
INSERT INTO system_message_flags (id, label) VALUES (3, 'Missing Mandatory Fields');
INSERT INTO system_message_flags (id, label) VALUES (4, 'Coding/Vocabulary Errors');
INSERT INTO system_message_flags (id, label) VALUES (5, 'MQF Structural Errors');
INSERT INTO system_message_flags (id, label) VALUES (6, 'Data Entry Error');
INSERT INTO system_message_flags (id, label) VALUES (7, 'Fix Duplicate');
INSERT INTO system_message_flags (id, label) VALUES (8, 'Other');
INSERT INTO system_message_flags (id, label) VALUES (9, 'Needs Fixing');


--
-- TOC entry 4362 (class 0 OID 0)
-- Dependencies: 280
-- Name: system_message_flags_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('system_message_flags_id_seq', 9, true);


--
-- TOC entry 4364 (class 0 OID 0)
-- Dependencies: 294
-- Name: vocab_app_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('vocab_app_id_seq', 2, true);


--
-- TOC entry 4347 (class 0 OID 27995406)
-- Dependencies: 582
-- Data for Name: vocab_codeset; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO vocab_codeset (id, codeset_name) VALUES (1, 'ICD-10-CM');
INSERT INTO vocab_codeset (id, codeset_name) VALUES (2, 'ICD-9-CM');


--
-- TOC entry 4365 (class 0 OID 0)
-- Dependencies: 581
-- Name: vocab_codeset_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('vocab_codeset_id_seq', 2, true);


--
-- TOC entry 4942 (class 0 OID 31616609)
-- Dependencies: 852
-- Data for Name: pending_watch_list; Type: TABLE DATA; Schema: elr; Owner: droolsuser
--

INSERT INTO pending_watch_list (id, lname) VALUES (1, 'source');
INSERT INTO pending_watch_list (id, lname) VALUES (2, 'test');
INSERT INTO pending_watch_list (id, lname) VALUES (3, 'critical');
INSERT INTO pending_watch_list (id, lname) VALUES (4, 'trauma');
INSERT INTO pending_watch_list (id, lname) VALUES (5, 'Employee');
INSERT INTO pending_watch_list (id, lname) VALUES (6, 'CAP');
INSERT INTO pending_watch_list (id, lname) VALUES (7, 'Zzzuv');


--
-- TOC entry 4947 (class 0 OID 0)
-- Dependencies: 851
-- Name: pending_watch_list_id_seq; Type: SEQUENCE SET; Schema: elr; Owner: droolsuser
--

SELECT pg_catalog.setval('pending_watch_list_id_seq', 7, true);


-- Completed on 2016-11-18 15:45:39

--
-- PostgreSQL database dump complete
--

