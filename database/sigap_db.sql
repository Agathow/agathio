CREATE DATABASE IF NOT EXISTS sigap_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigap_db;
SET FOREIGN_KEY_CHECKS=0;

DROP VIEW IF EXISTS v_dashboard_summary;
DROP TABLE IF EXISTS report_evidence;
DROP TABLE IF EXISTS report_status_history;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS public_services;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('citizen','inspector','admin') NOT NULL DEFAULT 'citizen',
    phone VARCHAR(30) NULL,
    avatar VARCHAR(255) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role(role)
) ENGINE=InnoDB;

CREATE TABLE locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    province VARCHAR(100) NOT NULL,
    city VARCHAR(100) NULL,
    district VARCHAR(100) NULL,
    subdistrict VARCHAR(100) NULL,
    address TEXT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE service_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE public_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    institution VARCHAR(180) NULL,
    official_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
    regulation VARCHAR(255) NULL,
    duration_minutes INT UNSIGNED NULL,
    description TEXT NULL,
    requirements TEXT NULL,
    procedure_text TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES service_categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_service_status(status),
    INDEX idx_service_category(category_id)
) ENGINE=InnoDB;

CREATE TABLE reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(40) NOT NULL UNIQUE,
    access_code VARCHAR(40) NULL,
    user_id INT UNSIGNED NULL,
    service_id INT UNSIGNED NULL,
    location_id INT UNSIGNED NULL,
    report_mode ENUM('anonymous','account') NOT NULL DEFAULT 'anonymous',
    incident_type ENUM('additional_fee','no_receipt','delay','broker','other') NOT NULL DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    chronology TEXT NOT NULL,
    requested_amount DECIMAL(15,2) NULL,
    official_amount DECIMAL(15,2) NULL,
    discrepancy_amount DECIMAL(15,2) NULL,
    incident_at DATETIME NULL,
    risk_level ENUM('low','moderate','elevated','high') NOT NULL DEFAULT 'moderate',
    status ENUM('new','pending_review','under_review','follow_up','resolved','rejected') NOT NULL DEFAULT 'new',
    assigned_to INT UNSIGNED NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(service_id) REFERENCES public_services(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(location_id) REFERENCES locations(id) ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY(assigned_to) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_report_status(status),
    INDEX idx_report_risk(risk_level),
    INDEX idx_report_service(service_id),
    INDEX idx_report_location(location_id),
    INDEX idx_report_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE report_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    note TEXT NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(report_id) REFERENCES reports(id) ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY(changed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE report_evidence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(report_id) REFERENCES reports(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users(name,email,password_hash,role,is_verified) VALUES
('Admin Pemerintah','admin@sigap.local','$2y$12$T5t87.hXVCLnUGHIQWCZ5eIEmKsf5pmlgeod5nU4UnQle1Ws3KVnC','admin',1),
('Inspektor Utama','inspector@sigap.local','$2y$12$aj8Sy/ooPfN0q0Iveyvf.uJ/1.tgcNXV8VUeyRV8L5og1aK075sNa','inspector',1),
('Fidele Bagas Prakoso','fidele@example.com','$2y$12$FxEJWWWXWawDheWBMzdBuOdmgMiEhLXS/r/2MAFU//dGcWMyq8Dyy','citizen',1),
('Admin Pelayanan','pelayanan@sigap.local','$2y$12$T5t87.hXVCLnUGHIQWCZ5eIEmKsf5pmlgeod5nU4UnQle1Ws3KVnC','admin',1);

INSERT INTO service_categories(name,description) VALUES
('Kependudukan','Administrasi kependudukan'),
('Transportasi & SIM','Layanan SIM dan kendaraan'),
('Imigrasi','Layanan paspor dan imigrasi'),
('Kesehatan','Layanan kesehatan'),
('Perizinan Bisnis','Perizinan usaha dan OSS');

INSERT INTO locations(province,city,district,subdistrict,address,latitude,longitude) VALUES
('DKI Jakarta','Jakarta Barat','Kebon Jeruk','Kecamatan X','Satpas Wilayah Barat',-6.1967000,106.7684000),
('DKI Jakarta','Jakarta Timur','Duren Sawit','Kecamatan Y','Loket Dukcapil',-6.2330000,106.9200000),
('DKI Jakarta','Jakarta Selatan','Kebayoran Baru','Kecamatan Z','Loket PTSP',-6.2430000,106.7990000),
('DKI Jakarta','Jakarta Pusat','Menteng','Kecamatan A','Unit Pelayanan Imigrasi',-6.1944000,106.8320000);

INSERT INTO public_services(category_id,name,institution,official_fee,regulation,duration_minutes,description,requirements,procedure_text)
SELECT id,'Pembuatan SIM A Baru','Satpas Polri',120000,'PP No. 76 Tahun 2020',120,
'Kendaraan roda 4 perorangan dengan berat yang diperbolehkan tidak melebihi 3.500 kg.',
'e-KTP asli & fotokopi; Surat Keterangan Sehat; Surat Keterangan Lulus Tes Psikologi.',
'Registrasi & verifikasi berkas; Pembayaran PNBP; Ujian teori; Ujian praktik; Foto biometrik; Cetak SIM.'
FROM service_categories WHERE name='Transportasi & SIM';

INSERT INTO public_services(category_id,name,institution,official_fee,regulation,duration_minutes,description,requirements,procedure_text)
SELECT id,'Paspor Biasa 48 Halaman','Kantor Imigrasi',350000,'PP Tarif PNBP',180,
'Layanan penerbitan paspor biasa 48 halaman.',
'e-KTP; KK; dokumen pendukung identitas.',
'Registrasi; verifikasi; foto & biometrik; wawancara; pembayaran; pengambilan paspor.'
FROM service_categories WHERE name='Imigrasi';

INSERT INTO public_services(category_id,name,institution,official_fee,regulation,duration_minutes,description,requirements,procedure_text)
SELECT id,'KTP Elektronik / IKD','Dukcapil Kabupaten/Kota',0,'Ketentuan Administrasi Kependudukan',60,
'Layanan identitas kependudukan elektronik dan digital.',
'e-KTP/KK sesuai kebutuhan layanan.',
'Registrasi; verifikasi; perekaman; validasi; pencetakan/pengaktifan.'
FROM service_categories WHERE name='Kependudukan';

INSERT INTO public_services(category_id,name,institution,official_fee,regulation,duration_minutes,description,requirements,procedure_text)
SELECT id,'Perizinan Usaha OSS NIB','Kementerian Investasi / BKPM',0,'Regulasi OSS',90,
'Layanan perizinan usaha melalui sistem OSS.',
'Data identitas dan data usaha.',
'Registrasi OSS; pengisian data; validasi; penerbitan NIB.'
FROM service_categories WHERE name='Perizinan Bisnis';

INSERT INTO public_services(category_id,name,institution,official_fee,regulation,duration_minutes,description,requirements,procedure_text)
SELECT id,'Pendaftaran BPJS Kesehatan','BPJS Kesehatan',0,'Ketentuan BPJS',60,
'Layanan administrasi kepesertaan BPJS Kesehatan.',
'KTP dan KK.',
'Registrasi; verifikasi; pemilihan kepesertaan; aktivasi.'
FROM service_categories WHERE name='Kesehatan';

INSERT INTO reports(report_code,access_code,user_id,service_id,location_id,report_mode,incident_type,title,chronology,requested_amount,official_amount,discrepancy_amount,incident_at,risk_level,status,is_anonymous)
SELECT 'SIGAP-26-001284','X7K9-PQ28',3,s.id,l.id,'account','additional_fee',
'Pembuatan SIM Baru (Satpas Wilayah Barat)',
'Petugas di loket 3 meminta tambahan uang tunai Rp50.000 di luar biaya PNBP resmi tanpa memberikan kuitansi atau struk bukti setor.',
170000,120000,50000,'2026-09-22 10:30:00','elevated','under_review',1
FROM public_services s JOIN locations l ON l.city='Jakarta Barat'
WHERE s.name='Pembuatan SIM A Baru';

INSERT INTO reports(report_code,access_code,user_id,service_id,location_id,report_mode,incident_type,title,chronology,incident_at,risk_level,status,is_anonymous)
SELECT 'SIGAP-26-001201','AB12-CD34',3,s.id,l.id,'account','delay',
'Perekaman & Cetak KTP-el (Dukcapil Unit 3)',
'Keterlambatan cetak blanko pasca perekaman biometrik selama 14 hari kerja.',
'2026-09-18 12:00:00','moderate','follow_up',0
FROM public_services s JOIN locations l ON l.city='Jakarta Timur'
WHERE s.name='KTP Elektronik / IKD';

INSERT INTO reports(report_code,access_code,user_id,service_id,location_id,report_mode,incident_type,title,chronology,incident_at,risk_level,status,is_anonymous)
SELECT 'SIGAP-26-001155','EF56-GH78',3,s.id,l.id,'account','broker',
'Izin Usaha OSS & Rekomendasi (PTSP)',
'Pelapor menerima penawaran jalur percepatan melalui perantara di luar kanal resmi.',
'2026-09-12 09:15:00','elevated','resolved',1
FROM public_services s JOIN locations l ON l.city='Jakarta Selatan'
WHERE s.name='Perizinan Usaha OSS NIB';

INSERT INTO reports(report_code,access_code,user_id,service_id,location_id,report_mode,incident_type,title,chronology,requested_amount,official_amount,discrepancy_amount,incident_at,risk_level,status,is_anonymous) VALUES
('SIGAP-26-000001','AC000001',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 1 DAY,'high','under_review',1),
('SIGAP-26-000002','AC000002',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 2 DAY,'high','pending_review',1),
('SIGAP-26-000003','AC000003',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 3 DAY,'elevated','follow_up',1),
('SIGAP-26-000004','AC000004',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 4 DAY,'elevated','resolved',1),
('SIGAP-26-000005','AC000005',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 5 DAY,'elevated','new',1),
('SIGAP-26-000006','AC000006',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 6 DAY,'moderate','under_review',1),
('SIGAP-26-000007','AC000007',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 7 DAY,'moderate','pending_review',1),
('SIGAP-26-000008','AC000008',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 8 DAY,'low','follow_up',1),
('SIGAP-26-000009','AC000009',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 9 DAY,'low','resolved',1),
('SIGAP-26-000010','AC000010',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 10 DAY,'low','new',1),
('SIGAP-26-000011','AC000011',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 11 DAY,'high','under_review',1),
('SIGAP-26-000012','AC000012',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 12 DAY,'high','pending_review',1),
('SIGAP-26-000013','AC000013',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 13 DAY,'elevated','follow_up',1),
('SIGAP-26-000014','AC000014',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 14 DAY,'elevated','resolved',1),
('SIGAP-26-000015','AC000015',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 15 DAY,'elevated','new',1),
('SIGAP-26-000016','AC000016',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 16 DAY,'moderate','under_review',1),
('SIGAP-26-000017','AC000017',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 17 DAY,'moderate','pending_review',1),
('SIGAP-26-000018','AC000018',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 18 DAY,'low','follow_up',1),
('SIGAP-26-000019','AC000019',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 19 DAY,'low','resolved',1),
('SIGAP-26-000020','AC000020',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 20 DAY,'low','new',1),
('SIGAP-26-000021','AC000021',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 21 DAY,'high','under_review',1),
('SIGAP-26-000022','AC000022',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 22 DAY,'high','pending_review',1),
('SIGAP-26-000023','AC000023',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 23 DAY,'elevated','follow_up',1),
('SIGAP-26-000024','AC000024',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 24 DAY,'elevated','resolved',1),
('SIGAP-26-000025','AC000025',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 25 DAY,'elevated','new',1),
('SIGAP-26-000026','AC000026',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 26 DAY,'moderate','under_review',1),
('SIGAP-26-000027','AC000027',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 27 DAY,'moderate','pending_review',1),
('SIGAP-26-000028','AC000028',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 28 DAY,'low','follow_up',1),
('SIGAP-26-000029','AC000029',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 29 DAY,'low','resolved',1),
('SIGAP-26-000030','AC000030',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 0 DAY,'low','new',1),
('SIGAP-26-000031','AC000031',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 1 DAY,'high','under_review',1),
('SIGAP-26-000032','AC000032',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 2 DAY,'high','pending_review',1),
('SIGAP-26-000033','AC000033',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 3 DAY,'elevated','follow_up',1),
('SIGAP-26-000034','AC000034',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 4 DAY,'elevated','resolved',1),
('SIGAP-26-000035','AC000035',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 5 DAY,'elevated','new',1),
('SIGAP-26-000036','AC000036',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 6 DAY,'moderate','under_review',1),
('SIGAP-26-000037','AC000037',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 7 DAY,'moderate','pending_review',1),
('SIGAP-26-000038','AC000038',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 8 DAY,'low','follow_up',1),
('SIGAP-26-000039','AC000039',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 9 DAY,'low','resolved',1),
('SIGAP-26-000040','AC000040',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 10 DAY,'low','new',1),
('SIGAP-26-000041','AC000041',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 11 DAY,'high','under_review',1),
('SIGAP-26-000042','AC000042',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 12 DAY,'high','pending_review',1),
('SIGAP-26-000043','AC000043',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 13 DAY,'elevated','follow_up',1),
('SIGAP-26-000044','AC000044',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 14 DAY,'elevated','resolved',1),
('SIGAP-26-000045','AC000045',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 15 DAY,'elevated','new',1),
('SIGAP-26-000046','AC000046',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','additional_fee','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 16 DAY,'moderate','under_review',1),
('SIGAP-26-000047','AC000047',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','no_receipt','Pembayaran Tanpa Bukti — Perizinan','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 17 DAY,'moderate','pending_review',1),
('SIGAP-26-000048','AC000048',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','delay','Penawaran Jalur Cepat — Paspor','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 18 DAY,'low','follow_up',1),
('SIGAP-26-000049','AC000049',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','broker','Biaya Tambahan — SIM','Data simulasi untuk dashboard SIGAP.',NULL,NULL,NULL,NOW() - INTERVAL 19 DAY,'low','resolved',1),
('SIGAP-26-000050','AC000050',NULL,(SELECT id FROM public_services WHERE name='Pembuatan SIM A Baru' LIMIT 1),(SELECT id FROM locations WHERE city='Jakarta Barat' LIMIT 1),'anonymous','other','Keterlambatan Proses — Administrasi Kependudukan','Data simulasi untuk dashboard SIGAP.',170000,120000,50000,NOW() - INTERVAL 20 DAY,'low','new',1);

INSERT INTO report_status_history(report_id,old_status,new_status,note,changed_by)
SELECT id,NULL,status,'Laporan awal dimasukkan oleh seed database.',1 FROM reports;

CREATE VIEW v_dashboard_summary AS
SELECT
COUNT(*) total_reports,
SUM(status IN ('under_review','follow_up')) active_reports,
SUM(status='pending_review') pending_reports,
SUM(status='resolved') resolved_reports,
COUNT(DISTINCT location_id) affected_locations,
SUM(risk_level='high') high_risk,
SUM(risk_level='elevated') elevated_risk,
SUM(risk_level='moderate') moderate_risk,
SUM(risk_level='low') low_risk,
COALESCE(SUM(discrepancy_amount),0) total_discrepancy
FROM reports;

ALTER TABLE reports
  MODIFY COLUMN status ENUM(
    'new','pending_review','under_review','follow_up',
    'awaiting_admin_verification','awaiting_reporter_confirmation',
    'reporter_disputed','resolved','closed_no_response','rejected'
  ) NOT NULL DEFAULT 'new';

ALTER TABLE reports
  ADD COLUMN IF NOT EXISTS reporter_deadline_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS reporter_response ENUM('accepted','disputed') NULL,
  ADD COLUMN IF NOT EXISTS reporter_response_note TEXT NULL,
  ADD COLUMN IF NOT EXISTS reporter_response_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS closed_no_response_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS report_follow_ups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL,
  action_type ENUM(
    'clarification_request','initial_review','official_warning',
    'service_correction','official_fee_confirmation','other'
  ) NOT NULL DEFAULT 'other',
  action_date DATE NOT NULL,
  summary TEXT NOT NULL,
  public_note TEXT NULL,
  publication_status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_followup_report FOREIGN KEY (report_id) REFERENCES reports(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_followup_admin FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_followup_report_status (report_id, publication_status),
  INDEX idx_followup_date (action_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_follow_up_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  follow_up_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL UNIQUE,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_followup_evidence_followup FOREIGN KEY (follow_up_id) REFERENCES report_follow_ups(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_followup_evidence (follow_up_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS public_holidays (
  holiday_date DATE PRIMARY KEY,
  description VARCHAR(180) NOT NULL
) ENGINE=InnoDB;