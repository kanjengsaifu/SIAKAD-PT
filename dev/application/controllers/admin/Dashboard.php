<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Backend_Controller {

	protected $user_detail;
	protected $filename_backup_db = 'backup_db.zip';
	protected $filename_backup_tbl_db = 'backup_tbl_db.zip';
	protected $download_url = 'downloads/temp-download-path/';

	public function __construct(){
		parent::__construct();		
		$this->site->login_status_check();
	}
	
	public function index(){
		$thn = $this->thn_ajaran_model->get_by_search(array('status_jdl ' => 1),TRUE,array('thn_ajaran_jdl'));
		if ($thn) {
			$thn_akademik = thn_ajaran_conv($thn->thn_ajaran_jdl);
		}
		else{
			$thn_akademik = '-';
		}
		$data = array(
			'count_mhs' => number_format($this->mahasiswa_model->count(),0,',','.'), 
			'count_alumni' => number_format($this->alumni_model->count(),0,',','.'), 
			'count_ptk' => number_format($this->ptk_model->count(),0,',','.'), 
			'tahun_akademik' => $thn_akademik,
			'dashboard' => TRUE
			);
		$this->site->view('page/'.$this->router->class.'/'.$this->router->method,$data);
	}

	public function about(){
		$this->site->view('page/others/'.$this->router->method);
	}

	public function pusat_unggahan(){
		$this->page_soon('Pusat Unggahan','fa-cloud-upload');
	}

	public function data_content(){
		$this->page_soon('Data Content','fa-file-text');
	}

	public function pengolahan_database(){
		$this->site->view('page/others/'.$this->router->method);
	}

	public function pengaturan(){
		$this->site->view('page/others/'.$this->router->method,array('settings' => TRUE));
	}

	public function feedback(){
		$this->page_soon('Feedback','fa-comments-o');
	}

	public function data_statistik($param){
		$post = $this->input->post(NULL, TRUE);
		if ($param == 'pengguna') {
			if (!isset($_POST['id']) && !isset($_POST['level'])) {
				$jumlah_pengguna = $this->user_model->count();
				if ($jumlah_pengguna > 0) {
					$sub_query_count[] = '(SELECT COUNT(*) FROM {PRE}user WHERE level_akses = "mhs") AS mhs_user';
					$sub_query_count[] = '(SELECT COUNT(*) FROM {PRE}user WHERE level_akses = "ptk") AS ptk_user';
					$count = $this->user_model->get_by_search(NULL,TRUE,$sub_query_count);
					$count_user_mhs = $count->mhs_user;
					$count_user_ptk = $count->ptk_user;
					if ($count_user_mhs > 0) {
						$statik_pengguna_mhs = round($count_user_mhs/$jumlah_pengguna*100);
					}
					if ($count_user_ptk > 0) {
						$statik_pengguna_ptk = round($count_user_ptk/$jumlah_pengguna*100);
					}

					
					$sub_query_count_status[] = '(SELECT COUNT(*) FROM {PRE}user WHERE active_status = 1 AND level_akses != "admin") AS active_user';
					$sub_query_count_status[] = '(SELECT COUNT(*) FROM {PRE}user WHERE active_status = 0 AND level_akses != "admin") AS non_active_user';
					$count_status = $this->user_model->get_by_search(NULL,TRUE,$sub_query_count_status);
					$count_user_aktif = $count_status->active_user;
					$count_user_nonaktif = $count_status->non_active_user;
					$statik_pengguna_aktif = round($count_user_aktif/$jumlah_pengguna*100);
					$statik_pengguna_nonaktif = round($count_user_nonaktif/$jumlah_pengguna*100);

					$last_online = array(
						'level_akses !=' => 'admin',
						'last_online !=' => '0000-00-00 00:00:00',
						);
					$sub_query_user[] = 'CASE WHEN level_akses = "mhs" 
											THEN (SELECT nisn FROM {PRE}mahasiswa WHERE id = id_user_detail) 
										WHEN level_akses = "ptk" 
											THEN (SELECT nuptk FROM {PRE}ptk WHERE id_ptk = id_user_detail) 
										END AS username';
					$sub_query_user[] = 'CASE WHEN level_akses = "mhs" 
											THEN (SELECT nama FROM {PRE}mahasiswa WHERE id = id_user_detail) 
										WHEN level_akses = "ptk" 
											THEN (SELECT nama_ptk FROM {PRE}ptk WHERE id_ptk = id_user_detail) 
										END AS name';
					$select_fld_user = array_merge(array('id_user_detail','level_akses','last_online'),$sub_query_user);
					$record_user = $this->user_model->get_by($last_online,5,NULL,NULL,$select_fld_user);

					$result = array(
						'status' => 'success',
						'count_user_aktif' => $count_user_aktif, 
						'count_user_nonaktif' => $count_user_nonaktif, 
						'statik_pengguna_aktif' => $statik_pengguna_aktif,
						'statik_pengguna_nonaktif' => $statik_pengguna_nonaktif,
						'count_user_mhs' => $count_user_mhs, 			
						'count_user_ptk' => $count_user_ptk,
						'statik_pengguna_mhs' => $statik_pengguna_mhs,
						'statik_pengguna_ptk' => $statik_pengguna_ptk,
						'record_last_online' => $record_user
						);
				}
				else{
					$result = array('status' => 'empty');
				}
			}
			else{
				$vars = explode('-',$post['id']);
				if ($vars[1] == 'mhs') {
					$where = array('id' => $vars[0]);
					$total_rows = $this->mahasiswa_model->count($where);
				}
				else{
					$where = array('id_ptk' => $vars[0]);
					$total_rows = $this->ptk_model->count($where);
				}

				if ($total_rows > 0 && $vars[1] == 'mhs' || $vars[1] == 'ptk') {
					$record = array();
					if ($vars[1] == 'mhs') {
						$record_mhs = $this->mahasiswa_model->get_detail_data('get',array('prodi_mhs','thn_angkatan'),NULL,$where,FALSE,array('nama','nisn','nama_prodi','jenjang_prodi','tahun_angkatan','jk','tmp_lhr','tgl_lhr','nik','agama','alamat','rt','rw','dusun','kelurahan','kecamatan','kode_pos','jns_tinggal','alt_trans','tlp','hp','email'));
						foreach ($record_mhs as $key) {
							$tgl_lhr = array('tgl_lhr' => date_convert($key->tgl_lhr));
							$record[] = array_merge((array)$key,$tgl_lhr);
						}
					}
					elseif ($vars[1] == 'ptk') {
						$record_ptk = $this->ptk_model->get_detail_data('get',array('prodi_ptk'),NULL,$where,FALSE,array('nama_ptk','nuptk','nip','nama_prodi','jenjang_prodi','jk_ptk','tmp_lhr_ptk','tgl_lhr_ptk','status_ptk','status_aktif_ptk','jenjang'));
						foreach ($record_ptk as $key) {
							$arr = array(
								'tgl_lhr_ptk' => date_convert($key->tgl_lhr_ptk),
								'status_ptk' => select_conv_value($key->status_ptk,'ptk','status_ptk'),
								'status_aktif_ptk' => select_conv_value($key->status_aktif_ptk,'ptk','status_aktif_ptk'),
								'jenjang' => select_conv_value($key->jenjang,'ptk','jenjang'),
								);
							$record[] = array_merge((array)$key,$arr);
						}
					}
					$result = array(
						'total_rows' => $total_rows,
						'record' => $record,
						'data' => $vars[1]
						);
				}
				else{
					$result = array('total_rows' => $total_rows);
				}
			}
		}
		elseif ($param == 'pd') {
			$count_mhs = $this->mahasiswa_model->count();
			if ($count_mhs > 0) {
				$sub_query_count[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE id_pd_mhs = id_prodi) AS jml_mhs';
				$sub_query_count[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE id_pd_mhs = id_prodi AND jk = "L") AS mhs_lk';
				$sub_query_count[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE id_pd_mhs = id_prodi AND jk = "P") AS mhs_pr';
				$select_fld = array_merge(array('id_prodi','nama_prodi','jenjang_prodi'),$sub_query_count);
				$daftar_pd = $this->prodi_model->get_by_search(NULL,FALSE,$select_fld);

				$sub_query_count_thn[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE thn_angkatan = id_thn_angkatan) AS jml_mhs';
				$sub_query_count_thn[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE thn_angkatan = id_thn_angkatan AND jk = "L") AS mhs_lk';
				$sub_query_count_thn[] = '(SELECT COUNT(*) FROM {PRE}mahasiswa WHERE thn_angkatan = id_thn_angkatan AND jk = "P") AS mhs_pr';
				$select_fld_thn = array_merge(array('id_thn_angkatan','tahun_angkatan'),$sub_query_count_thn);
				$daftar_thn = $this->thn_angkatan_model->get_by(NULL,4,NULL,FALSE,$select_fld_thn);

				/*$prodi = array();
				$canvas = array();
				$nama_prodi = array();
				$mhs_lk = array();
				$mhs_pr = array();
				$color = array();*/
				$no = 0;
				$pd = 1;
				foreach ($daftar_pd as $key) {
					$count_mhs_pd = $key->jml_mhs;
					$statik_mhs_pd = $count_mhs_pd/$count_mhs*100;
					$detail_grafik = array(
						'count_mhs' => number_format($count_mhs_pd,0,',','.'),
						'statik_mhs' => round($statik_mhs_pd),
						'color_detail' => color_pd_static($no),
						'no_prodi' => 'Prodi '.$pd
						);
					$prodi[] = array_merge((array)$key,$detail_grafik);
					$canvas[] = array(
						'value' => $count_mhs_pd,
						'color' => color_pd_static($no),
						'highlight' => color_pd_static($no),
						'label' => $key->nama_prodi.' ('.$key->jenjang_prodi.')',
						);
					$nama_prodi[] = 'Prodi '.$pd;
					$color[] = color_pd_static($no);
					$mhs_lk[] = $key->mhs_lk;
					$mhs_pr[] = $key->mhs_pr;
					$no++;
					$pd++;
				}

				/*$thn = array();
				$thn_angkatan = array();
				$mhs_lk_thn = array();
				$mhs_pr_thn = array();
				$color_thn = array();*/
				$no = 7;
				foreach ($daftar_thn as $key) {
					$count_mhs_thn = $key->jml_mhs;
					$statik_mhs_thn = $count_mhs_thn/$count_mhs*100;
					$detail_grafik = array(
						'statik_mhs' => round($statik_mhs_thn),
						'color_detail' => color_pd_static($no),
						);
					$thn[] = array_merge((array)$key,$detail_grafik);
					$thn_angkatan[] = 'Tahun '.$key->tahun_angkatan;
					$color_thn[] = color_pd_static($no);
					$mhs_lk_thn[] = $key->mhs_lk;
					$mhs_pr_thn[] = $key->mhs_pr;
					$no++;
				}
				$result = array(
					'status' => 'success',
					'pd' => $prodi,
					'canvas' => $canvas,
					'nama_prodi' => $nama_prodi,
					'mhs_lk' => $mhs_lk,
					'mhs_pr' => $mhs_pr,
					'color' => $color,
					'thn' => $thn,
					'thn_angkatan' => $thn_angkatan,
					'mhs_lk_thn' => $mhs_lk_thn,
					'mhs_pr_thn' => $mhs_pr_thn,
					'color_thn' => $color_thn,
					);
			}
			else{
				$result = array('status' => 'empty');
			}
		}
		elseif ($param == 'ptk') {
			$count_ptk = $this->ptk_model->count();
			if ($count_ptk > 0) {
				$sub_query_ptk[] = '(SELECT COUNT(*) FROM {PRE}ptk WHERE jurusan_prodi = id_prodi) AS count_ptk';
				$select_fld_ptk = array_merge(array('id_prodi','nama_prodi','jenjang_prodi'),$sub_query_ptk);
				$daftar_pd = $this->prodi_model->get_by_search(NULL,FALSE,$select_fld_ptk);
				$prodi = array();
				$canvas = array();
				$no = 0;
				foreach ($daftar_pd as $key) {
					$count_ptk_pd = $key->count_ptk;
					$statik_ptk_pd = $count_ptk_pd/$count_ptk*100;
					$detail_grafik = array(
						'statik_ptk' => round($statik_ptk_pd),
						'color_detail' => color_pd_static($no)
						);
					$prodi[] = array_merge((array)$key,$detail_grafik);
					$canvas[] = array(
						'value' => $count_ptk_pd,
						'color' => color_pd_static($no),
						'highlight' => color_pd_static($no),
						'label' => $key->nama_prodi.' ('.$key->jenjang_prodi.')',
						);
					$no++;
				}
				$result = array(
					'status' => 'success',
					'pd' => $prodi,
					'canvas' => $canvas,
					);
			}
			else{
				$result = array('status' => 'empty');
			}
		}
		else{
			$result = array('status_action' => 'Not find...');
		}
		$result['n_token'] = $this->security->get_csrf_hash();
		echo json_encode($result);
	}

	public function action($param){
		$post = $this->input->post(NULL, TRUE);
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			if ($param == 'tambah') {
				if (isset($post['data_menu'])) {
					$rules = $this->main_menu_list_model->rules;
					$this->form_validation->set_rules($rules);

					if ($this->form_validation->run() == TRUE) {
						$data_menu = array(							
							'nm_menu' => ucwords($post['nm_menu']),
							'level_access_menu' => $post['level_access_menu'],
							'status_access_menu' => $post['status_access_menu'],
							'link_menu' => strtolower($post['link_menu']),
							'color_menu' => strtolower($post['color_menu']),
							'icon_menu' => strtolower($post['icon_menu']),
							);
						$save_data_menu = $this->main_menu_list_model->insert($data_menu);
						if ($save_data_menu) {
							$data = 'data_menu';
							$result = array(
								'status' => 'success',
								'data' => $data
								);							
						}
						else{
							$result = array('status' => 'failed_db');
						}
					}
					else {
						$result = array(
							'status' => 'failed',
							'errors'=> $this->form_validation->error_array()
							);
					}
				}
				elseif (isset($post['data_sub_menu'])) {
					$rules = $this->sub_menu_list_model->rules;
					$this->form_validation->set_rules($rules);

					if ($this->form_validation->run() == TRUE) {
						$data_menu = array(							
							'id_parent_menu' => $post['id_parent_menu'],
							'nm_sub_menu' => ucwords($post['nm_sub_menu']),
							'status_access_sub_menu' => $post['status_access_sub_menu'],
							'link_sub_menu' => strtolower($post['link_sub_menu']),
							'icon_sub_menu' => strtolower($post['icon_sub_menu'])
							);
						$save_data_menu = $this->sub_menu_list_model->insert($data_menu);
						if ($save_data_menu) {
							$data = 'data_menu';
							$result = array(
								'status' => 'success',
								'data' => $data
								);							
						}
						else{
							$result = array('status' => 'failed_db');
						}
					}
					else {
						$result = array(
							'status' => 'failed',
							'errors'=> $this->form_validation->error_array()
							);
					}
				}
			}
			elseif ($param == 'update') {
				if (isset($post['data_menu'])) {
					$rules = $this->main_menu_list_model->rules;
					$this->form_validation->set_rules($rules);

					if ($this->form_validation->run() == TRUE) {
						$data_menu = array(							
							'nm_menu' => ucwords($post['nm_menu']),
							'level_access_menu' => $post['level_access_menu'],
							'status_access_menu' => $post['status_access_menu'],
							'link_menu' => strtolower($post['link_menu']),
							'color_menu' => strtolower($post['color_menu']),
							'icon_menu' => strtolower($post['icon_menu']),
							);
						$update_data_menu = $this->main_menu_list_model->update($data_menu,array('id_menu' => $post['id_menu']));
						if ($update_data_menu) {
							$data = 'data_menu';
							$result = array(
								'status' => 'success',
								'data' => $data
								);							
						}
						else{
							$result = array('status' => 'failed_db');
						}
					}
					else {
						$result = array(
							'status' => 'failed',
							'errors'=> $this->form_validation->error_array()
							);
					}
				}
				elseif (isset($post['data_sub_menu'])) {
					$rules = $this->sub_menu_list_model->rules;
					$this->form_validation->set_rules($rules);

					if ($this->form_validation->run() == TRUE) {
						$data_menu = array(							
							'id_parent_menu' => $post['id_parent_menu'],
							'nm_sub_menu' => ucwords($post['nm_sub_menu']),
							'status_access_sub_menu' => $post['status_access_sub_menu'],
							'link_sub_menu' => strtolower($post['link_sub_menu']),
							'icon_sub_menu' => strtolower($post['icon_sub_menu'])
							);
						$update_data_menu = $this->sub_menu_list_model->update($data_menu,array('id_sub_menu' => $post['id_sub_menu']));
						if ($update_data_menu) {
							$data = 'data_menu';
							$result = array(
								'status' => 'success',
								'data' => $data
								);							
						}
						else{
							$result = array('status' => 'failed_db');
						}
					}
					else {
						$result = array(
							'status' => 'failed',
							'errors'=> $this->form_validation->error_array()
							);
					}
				}
				elseif (isset($post['data']) && $post['data'] == 'sorting_menu') {
					/*if ($post['menu_type'] == 'main-menu') {
						$id = $post['list_menu'];
						if ($this->check_list_menu($id,$post['level'],$post['menu_type']) == TRUE) {
							$id_menu = array();
							$no = 1;
							foreach ($id as $key) {
								$id_menu[] = array(
									'id_menu' => $key,
									'sort_menu' => $no,
									);
								$no++;
							}
							$update_list_menu = $this->main_menu_list_model->update($id_menu,'id_menu',TRUE);
							if ($update_list_menu > 0) {
								$result = array(
									'status' => 'success'
								);
							}
							else{
								$result = array(
									'status' => 'failed',
									'message' => 'Gagal memperbahrui urutan menu',
								);
							}
						}
						else{
							$result = array(
								'status' => 'failed_list',
								'message' => 'Gagal memperbahrui urutan menu karena urutan menu yang tidak valid',
							);
						}
					}
					elseif ($post['menu_type'] == 'sub-menu') {
						
					}*/
					$id = $post['list_menu'];
					if ($this->check_list_menu($id,$post['level'],$post['menu_type'],@$post['parent_menu'],@$post['move_sub'],$post['menu']) == TRUE) {
						$id_menu = array();
						$no = 1;
						foreach ($id as $key) {
							if ($post['menu_type'] == 'main-menu') {
								$id_menu[] = array(
									'id_menu' => $key,
									'sort_menu' => $no,
									);
							}
							elseif ($post['menu_type'] == 'sub-menu') {
								$id_menu[] = array(
									'id_sub_menu' => $key,
									'id_parent_menu' => $post['parent_menu'],
									'sort_sub_menu' => $no,
									);
							}
							$no++;
						}
						if ($post['menu_type'] == 'main-menu') {
							$update_list_menu = $this->main_menu_list_model->update($id_menu,'id_menu',TRUE);
						}
						elseif ($post['menu_type'] == 'sub-menu') {
							$update_list_menu = $this->sub_menu_list_model->update($id_menu,'id_sub_menu',TRUE);
						}

						if (isset($update_list_menu) && $update_list_menu > 0) {
							$result = array(
								'status' => 'success'
							);
						}
						elseif (isset($update_list_menu)) {
							$result = array(
								'status' => 'failed',
								'message' => 'Gagal memperbahrui urutan menu',
							);
						}
					}
					else{
						$result = array(
							'status' => 'failed_list',
							'message' => 'Gagal memperbahrui urutan menu karena urutan menu yang tidak valid',
						);
					}
				}
			}
			elseif ($param == 'delete') {
				if (isset($post['data_menu'])) {
					$delete_menu_by = array('id_menu' => $post['id_menu']);
					$delete_menu = $this->main_menu_list_model->delete_by($delete_menu_by);
					if ($delete_menu) {
						$data = 'data_menu';
						$result = array(
							'status' => 'success',
							'data' => $data
							);
					}
					else{
						$result = array('status' => 'failed_db');
					}
				}
				elseif (isset($post['data_sub_menu'])) {
					$delete_menu_by = array('id_sub_menu' => $post['id_sub_menu']);
					$delete_menu = $this->sub_menu_list_model->delete_by($delete_menu_by);
					if ($delete_menu) {
						$data = 'data_menu';
						$result = array(
							'status' => 'success',
							'data' => $data
							);
					}
					else{
						$result = array('status' => 'failed_db');
					}
				}
				elseif (isset($post['data']) && $post['data'] == 'backup_file') {
					$this->load->helper('file');
					if (get_file_info(get_real_path('/'.$this->download_url.$post['file'])) != FALSE) {
						$result = array(
							'status' => unlink($this->download_url.$post['file']),
						);
					}
					else{
						$result = array(
							'status' => 'File tidak ditemukan',
						);
					}
				}
			}
			elseif ($param == 'ambil') {
				if ($post['data'] == 'data_menu') {
					$id = array('id_menu' => $post['in_menu']);
					$total_rows = $this->main_menu_list_model->count($id);
					if ($total_rows > 0 ) {
						$record_menu = $this->main_menu_list_model->get_by_search($id,FALSE,Array('id_menu','nm_menu','status_access_menu','level_access_menu','link_menu','color_menu','icon_menu'));
						$result = array(
								'total_rows' => $total_rows,
								'record_menu' => $record_menu,
								);					
					}
					else{
						$result = array(
								'total_rows' => $total_rows,
								'message' => 'Data menu yang anda pilih tidak ditemukan / data telah dihapus'
								);
					}
				}
				elseif ($post['data'] == 'data_sub_menu') {
					$id = array('id_sub_menu' => $post['in_menu']);
					$total_rows = $this->sub_menu_list_model->count($id);
					if ($total_rows > 0 ) {
						$record_menu = $this->sub_menu_list_model->get_detail_data('get',array('main_menu_list'),NULL,$id,FALSE,Array('id_sub_menu','id_menu AS in_menu','nm_sub_menu','nm_menu','status_access_sub_menu','link_sub_menu','icon_sub_menu'));
						$result = array(
								'total_rows' => $total_rows,
								'record_menu' => $record_menu,
								);					
					}
					else{
						$result = array(
								'total_rows' => $total_rows,
								'message' => 'Data sub menu yang anda pilih tidak ditemukan / data telah dihapus'
								);
					}
				}
				elseif ($post['data']=='daftar_menu') {
					$cari = $post['value'];
					$act = array(
						'like' => array('nm_menu' => $cari)
					);
					$data = $this->main_menu_list_model->get_detail_data('get',NULL,$act);
					$total_rows = count($data);
					if ($total_rows > 0 ) {
						foreach ($data as $key => $value) {
							if ($value->level_access_menu == 'mhs') {
								$level = 'Mahasiswa';
							}
							elseif ($value->level_access_menu == 'ptk') {
								$level = 'Tenaga Pendidik';
							}
							else{
								$level = $value->level_access_menu;
							}

							if ($value->status_access_menu == 0) {
								$status = 'Dalam Pengembagan';
							}
							elseif ($value->status_access_menu == 1) {
								$status = 'Aktif';
							}
							elseif ($value->status_access_menu == 2) {
								$status = 'BETA';
							}
							elseif ($value->status_access_menu == 3) {
								$status = 'Dalam Perbaikan';
							}

							if ($value->level_access_menu == 'admin') {
								$link = set_url($value->link_menu);
							}
							else{
								$link = base_url($value->link_menu);
							}

							$record[] = array(
								'id' => $value->id_menu,
								'text' => $value->nm_menu,
								'level' => $level,
								'status' => $status,
								'icon' => $value->icon_menu,
								'color' => $value->color_menu,
								'link' => $link
								);
						}		
						$result = array(
							'menu' => $record,
							'total_count' => $total_rows
							);
					}					
					else{
						$result['menu'] = '';
					}					
				}
				elseif ($post['data'] == 'list_menu') {
					if ($post['menu'] == 'admin-menu') {
						$act = array(
							'where' => array(
								'level_access_menu' => 'admin',
							)
						);
					}
					elseif ($post['menu'] == 'user-menu') {
						$act = array(
							'where' => array(
								'level_access_menu !=' => 'admin'
							)
						);
					}

					$main_menu = $this->main_menu_list_model->get_detail_data('get',NULL,@$act,NULL,FALSE,array('id_menu','nm_menu','level_access_menu','sort_menu','icon_menu','color_menu','status_access_menu','link_menu'));
					$sub_menu = $this->sub_menu_list_model->get_detail_data('get',array('main_menu_list'),@$act,NULL,FALSE,array('id_sub_menu','id_parent_menu','nm_sub_menu','sort_sub_menu','icon_sub_menu','status_access_sub_menu','link_sub_menu','link_menu','level_access_menu'));
					$data_menu = array();
					foreach ($main_menu as $key) {
						if ($key->level_access_menu == 'admin') {
							$url_menu = set_url($key->link_menu);
						}
						else{
							$url_menu = base_url($key->link_menu);
						}

						$data_sub_menu = array();
						foreach ($sub_menu as $key_sub) {
							if ($key->id_menu == $key_sub->id_parent_menu) {
								if ($key_sub->status_access_sub_menu == 0) {
									$status = 'Dalam Pengembagan';
								}
								elseif ($key_sub->status_access_sub_menu == 1) {
									$status = 'Aktif';
								}
								elseif ($key_sub->status_access_sub_menu == 2) {
									$status = 'BETA';
								}
								elseif ($key_sub->status_access_sub_menu == 3) {
									$status = 'Dalam Perbaikan';
								}
								$link_sub_menu = $url_menu.'/'.$key_sub->link_sub_menu;
								$data_sub_menu[] = array_merge((array)$key_sub,array('sort_link' => $key_sub->link_sub_menu,'status' => $status,'link_sub_menu' => $link_sub_menu));
							}
						}
						if ($key->status_access_menu == 0) {
							$status = 'Dalam Pengembagan';
						}
						elseif ($key->status_access_menu == 1) {
							$status = 'Aktif';
						}
						elseif ($key->status_access_menu == 2) {
							$status = 'BETA';
						}
						elseif ($key->status_access_menu == 3) {
							$status = 'Dalam Perbaikan';
						}
						$data_menu[] = array_merge((array)$key,array('sort_link' => $key->link_menu,'sub_menu' => $data_sub_menu,'status' => $status,'link_menu' => $url_menu));
					}

					$new_dt_sub_menu = array();
					foreach ($sub_menu as $key_sub) {
						$url_sub_menu = '';
						if ($key_sub->level_access_menu == 'admin') {
							$url_sub_menu = set_url($key_sub->link_menu.'/'.$key_sub->link_sub_menu);
						}
						elseif ($key_sub->level_access_menu != '' && $key_sub->level_access_menu != 'admin') {
							$url_sub_menu = base_url($key_sub->link_menu.'/'.$key_sub->link_sub_menu);
						}

						$new_dt_sub_menu[] = array_merge((array)$key_sub,array('link_sub_menu' => @$url_sub_menu));
					}

					$this->session->set_userdata(array('menu' => $data_menu));

					$result = array(
						'total_rows' => count($data_menu),
						'parents_menu' => $data_menu,
						'sub_menu' => $new_dt_sub_menu
					);
				}
				elseif ($post['data'] == 'list_table_db') {
					$tables = $this->db->list_tables();
					$list_tbl = array();
					foreach ($tables as $table) {
						$or_tbl = str_replace('_', ' ', $table);
						$new_tbl_name = ucwords(str_replace('tbl', 'Tabel', $or_tbl));
				        $list_tbl[] = array(
				        	'id' => $table,
							'text' => $new_tbl_name,
			        	);
					}
					$result = array(
						'list_tbl' => $list_tbl,
						);
				}
				elseif ($post['data'] == 'general_conf') {
					global $Config;
					$result = array('config' => $Config);
				}
				elseif ($post['data'] == 'backup_file') {
					$this->load->helper('file');
					global $Config;
					$download_path = base_url().$this->download_url;

					/*Backup DB*/
					date_default_timezone_set("Asia/Makassar");
					$data_backup = get_file_info(get_real_path('/'.$this->download_url.$this->filename_backup_db),array('name','size','date'));
					if ($data_backup != FALSE) {
						$arr = array(
							'date' => date("Y-m-d H:i:s", $data_backup['date']),
							);
						$data_backup = array_merge((array)$data_backup,$arr);
					}

					$backup_db = array(
						'backup_db_name' => $this->filename_backup_db,
						'download_path' => $download_path.$this->filename_backup_db,
						'backup_detail' => $data_backup
						);
					/*END -- Backup DB*/

					/*Backup table DB*/
					$data_backup_tbl = get_file_info(get_real_path('/'.$this->download_url.$this->filename_backup_tbl_db),array('name','size','date'));
					if ($data_backup_tbl != FALSE) {
						$arr = array(
							'date' => date("Y-m-d H:i:s", $data_backup_tbl['date']),
							);
						$data_backup_tbl = array_merge((array)$data_backup_tbl,$arr);
					}

					$backup_tbl_db = array(
						'backup_db_name' => $this->filename_backup_tbl_db, 
						'download_path' => $download_path.$this->filename_backup_tbl_db,
						'backup_detail' => $data_backup_tbl
						);
					/*END -- Backup table DB*/

					$result = array(
						'backup_db' => $backup_db,
						/*'backup_db' => $data_backup,*/
						'backup_db_tbl' => $backup_tbl_db,
						/*'backup_tbl_db' => $data_backup_tbl,*/
						);
				}
			}
			elseif ($param == 'backup') {
				if ($post['data'] == 'backup_db') {
					$result = $this->backup_database();
				}
			}
		}
		elseif (!isset($result)) {
			$result = array(
				"success" => FALSE,
				"info" => "Service not found or not set",
				);
		}
		$result['n_token'] = $this->security->get_csrf_hash();
		echo json_encode($result);
	}

	public function temp_upload(){

	}

	public function html_request(){
		$post = $this->input->post(NULL, TRUE);
		$this->load->view("html_request/".$post['pg']."/".$post['request']);
	}

	protected function backup_database(){
		global $Config;
		$post = $this->input->post(NULL, TRUE);
		$this->user_detail = $this->user_admin_model->get_by(array('username' => $post['user'], 'level_akses' => 'admin'),1,NULL,TRUE);
		$user_detail = $this->user_detail;
		$user_check = $this->password_check($post['user'],$post['pass']);
		if ($user_check == 'TRUE') {
			$this->load->helper('file');
			$download_path = base_url().$this->download_url;

			if (!isset($post['data_opsi']) || $post['data_opsi'] == "TRUE") {
				$prefs = array(
			        'tables'        => array(),
			        'ignore'        => array(),
			        'format'        => 'zip',
			        'add_drop'      => TRUE,
			        'add_insert'    => TRUE,
				);
			}
			else{
				$prefs = array(
			        'tables'        => array(),
			        'ignore'        => array(),
			        'format'        => 'zip',
			        'add_drop'      => TRUE,
			        'add_insert'    => FALSE,
				);
			}

			if ($post['db_backup'] == 'full-backup') {
				$prefs['filename'] = $this->filename_backup_db;
				$this->load->dbutil();
				$backup = $this->dbutil->backup($prefs);

				$path = $Config->_document_root.'/downloads/temp-download-path/'.$this->filename_backup_db;
				$make_backup = write_file($path, $backup);
				if ($make_backup) {
					$result = array(
						'status' => 'success',
						'url_download' => $download_path.$prefs['filename'],
						);
				}
				else{
					$result = array('status' => 'failed');
				}
			}
			elseif ($post['db_backup'] == 'table-backup') {
				if ($post['table'] != '') {
					$prefs['filename'] = $this->filename_backup_tbl_db;
					$prefs['tables'] = $post['table'];
					$this->load->dbutil();
					$backup = $this->dbutil->backup($prefs);

					$path = $Config->_document_root.'/downloads/temp-download-path/'.$this->filename_backup_tbl_db;
					$make_backup = write_file($path, $backup);
					if ($make_backup) {
						$result = array(
							'status' => 'success',
							'url_download' => $download_path.$prefs['filename'],
							);
					}
					else{
						$result = array('status' => 'failed');
					}
				}
				else{
					$result = array(
						'status' => 'failed_db_tbl',
						'error' => 'Silahkan pilih tabel yang ingin di backup'
						);
				}
			}
		}
		else{
			$result = array(
				'status' => 'failed_auth',
				'error' => $user_check
				);
		}
		return @$result;
		/*$result['n_token'] = $this->security->get_csrf_hash();
		echo json_encode($result);*/
	}

	// public function backup_db_file(){
	// 	$this->load->helper('file');
	// 	$post = $this->input->post(NULL, TRUE);
	// 	global $Config;
	// 	if ($post['act'] == 'get_file') {
	// 		$download_path = base_url().$this->download_url;

	// 		/*Backup DB*/
	// 		date_default_timezone_set("Asia/Makassar");
	// 		$data_backup = get_file_info(get_real_path('/'.$this->download_url.$this->filename_backup_db),array('name','size','date'));
	// 		if ($data_backup != FALSE) {
	// 			$arr = array(
	// 				'date' => date("Y-m-d H:i:s", $data_backup['date']),
	// 				);
	// 			$data_backup = array_merge((array)$data_backup,$arr);
	// 		}

	// 		$backup_db = array(
	// 			'backup_db_name' => $this->filename_backup_db,
	// 			'download_path' => $download_path.$this->filename_backup_db,
	// 			'backup_detail' => $data_backup
	// 			);
	// 		/*END -- Backup DB*/

	// 		/*Backup table DB*/
	// 		$data_backup_tbl = get_file_info(get_real_path('/'.$this->download_url.$this->filename_backup_tbl_db),array('name','size','date'));
	// 		if ($data_backup_tbl != FALSE) {
	// 			$arr = array(
	// 				'date' => date("Y-m-d H:i:s", $data_backup_tbl['date']),
	// 				);
	// 			$data_backup_tbl = array_merge((array)$data_backup_tbl,$arr);
	// 		}

	// 		$backup_tbl_db = array(
	// 			'backup_db_name' => $this->filename_backup_tbl_db, 
	// 			'download_path' => $download_path.$this->filename_backup_tbl_db,
	// 			'backup_detail' => $data_backup_tbl
	// 			);
	// 		/*END -- Backup table DB*/

	// 		$result = array(
	// 			'backup_db' => $backup_db,
	// 			/*'backup_db' => $data_backup,*/
	// 			'backup_db_tbl' => $backup_tbl_db,
	// 			/*'backup_tbl_db' => $data_backup_tbl,*/
	// 			);
	// 	}
	// 	elseif ($post['act'] == 'delete_backup') {
	// 		if (get_file_info(get_real_path('/'.$this->download_url.$post['file'])) != FALSE) {
	// 			$result = array(
	// 				'status' => unlink($this->download_url.$post['file']),
	// 			);
	// 		}
	// 		else{
	// 			$result = array(
	// 				'status' => 'File tidak ditemukan',
	// 			);
	// 		}
	// 	}
	// 	$result['n_token'] = $this->security->get_csrf_hash();
	// 	echo json_encode($result);
	// }

	/*public function menu_list(){
		$post = $this->input->post(NULL, TRUE);
		if ($post['act'] == 'get') {
			if ($post['data'] == 'admin-menu') {
				$act = array(
					'where' => array(
						'level_access_menu' => 'admin',
					)
				);
			}
			elseif ($post['data'] == 'user-menu') {
				$act = array(
					'where' => array(
						'level_access_menu !=' => 'admin'
					)
				);
			}

			$main_menu = $this->main_menu_list_model->get_detail_data('get',NULL,@$act,NULL,FALSE,array('id_menu','nm_menu','level_access_menu','sort_menu','icon_menu','color_menu','status_access_menu','link_menu'));
			$sub_menu = $this->sub_menu_list_model->get_detail_data('get',array('main_menu_list'),@$act,NULL,FALSE,array('id_sub_menu','id_parent_menu','nm_sub_menu','sort_sub_menu','icon_sub_menu','status_access_sub_menu','link_sub_menu'));
			$data_menu = array();
			foreach ($main_menu as $key) {
				if ($key->level_access_menu == 'admin') {
					$url_menu = set_url($key->link_menu);
				}
				else{
					$url_menu = base_url($key->link_menu);
				}

				$data_sub_menu = array();
				foreach ($sub_menu as $key_sub) {
					if ($key->id_menu == $key_sub->id_parent_menu) {
						if ($key_sub->status_access_sub_menu == 0) {
							$status = 'Dalam Pengembagan';
						}
						elseif ($key_sub->status_access_sub_menu == 1) {
							$status = 'Aktif';
						}
						elseif ($key_sub->status_access_sub_menu == 2) {
							$status = 'Dalam Perbaikan';
						}
						$link_sub_menu = $url_menu.'/'.$key_sub->link_sub_menu;
						$data_sub_menu[] = array_merge((array)$key_sub,array('status' => $status,'link_sub_menu' => $link_sub_menu));
					}
				}
				if ($key->status_access_menu == 0) {
					$status = 'Dalam Pengembagan';
				}
				elseif ($key->status_access_menu == 1) {
					$status = 'Aktif';
				}
				elseif ($key->status_access_menu == 2) {
					$status = 'BETA';
				}
				elseif ($key->status_access_menu == 3) {
					$status = 'Dalam Perbaikan';
				}
				$data_menu[] = array_merge((array)$key,array('sub_menu' => $data_sub_menu,'status' => $status,'link_menu' => $url_menu));
			}

			global $Config;
			$result = array(
				'total_rows' => count($data_menu),
				'parents_menu' => $data_menu,
				'sub_menu' => $sub_menu,
				'test' => $Config
			);
		}
		elseif ($post['act'] == 'sorting_menu') {
			$id = $post['list_menu'];
			if ($this->check_list_menu($id,$post['level'],$post['menu_type'],@$post['parent_menu'],@$post['move_sub']) == TRUE) {
				$id_menu = array();
				$no = 1;
				foreach ($id as $key) {
					if ($post['menu_type'] == 'main-menu') {
						$id_menu[] = array(
							'id_menu' => $key,
							'sort_menu' => $no,
							);
					}
					elseif ($post['menu_type'] == 'sub-menu') {
						$id_menu[] = array(
							'id_sub_menu' => $key,
							'id_parent_menu' => $post['parent_menu'],
							'sort_sub_menu' => $no,
							);
					}
					$no++;
				}
				if ($post['menu_type'] == 'main-menu') {
					$update_list_menu = $this->main_menu_list_model->update($id_menu,'id_menu',TRUE);
				}
				elseif ($post['menu_type'] == 'sub-menu') {
					$update_list_menu = $this->sub_menu_list_model->update($id_menu,'id_sub_menu',TRUE);
				}

				if (isset($update_list_menu) && $update_list_menu > 0) {
					$result = array(
						'status' => 'success'
					);
				}
				elseif (isset($update_list_menu)) {
					$result = array(
						'status' => 'failed',
						'message' => 'Gagal memperbahrui urutan menu',
					);
				}
			}
			else{
				$result = array(
					'status' => 'failed_list',
					'message' => 'Gagal memperbahrui urutan menu karena urutan menu yang tidak valid',
				);
			}
		}

		if (!isset($result)) {
			$result = array('status_action' => 'Not find...');
		}
		$result['n_token'] = $this->security->get_csrf_hash();
		echo json_encode($result);
	}*/

	public function password_check($user,$pass){
		if (!empty($user)) {
			$user_detail = $this->user_detail;
			if (!empty($pass)) {
				if (@$user_detail->password == crypt($pass,@$user_detail->password)) {
					$result = 'TRUE';
					return $result;
				}
				elseif (@$user_detail->password) {
					$result = 'Username atau password anda salah';
					return $result;
				}
				else{
					$result = 'Username yang anda masukkan salah';
					return $result;
				}
			}
			else{
				$result = 'Password kosong, isi dengan benar';
				return $result;
			}			
		}
		else{
			$result = 'Username kosong, isi dengan benar';
			return $result;
		}
	}

	protected function update_password(){
		$post = $this->input->post(NULL, TRUE);
		if (!empty($post['password_lama']) && !empty($post['password_baru'])) {
			$password_lama = $post['password_lama'];
			$password_baru = $post['password_baru'];
			if (strlen($password_baru) >= 5) {
				$where_password = array(
					'username' => 'admin',
					'uncrypt_password' => $password_lama,
					);
				$check_password = $this->user_model->count($where_password);
				if ($check_password == 1) {
					$password_crypt = bCrypt($password_baru,12);
					$update_data = array(						
						'password' => $password_crypt,
						'uncrypt_password' => $password_baru,
						);
					$update_user = $this->user_model->update($update_data,$where_password);
					if ($update_user) {
						$result = array('status' => 'success', );
					}
					else{
						$result = array('status' => 'failed_update', );
					}
				}
				elseif ($check_password > 1) {
					$result = array('status' => 'more_row', );
				}
				else{
					$result = array('status' => 'failed', );
				}
			}
			else{
				$result = array('status' => 'form_validation_failed', );
			}			
		}
		else{
			$result = array('status' => 'failed_validation', );
		}
		$result['n_token'] = $this->security->get_csrf_hash();
		echo json_encode($result);
	}

	protected function check_list_menu($data,$level,$menu_type,$parent,$move_sub,$menu){
		if ($menu_type == 'main-menu') {
			if ($level == 'admin') {
				$act = array(
					'in' => array(
						'id_menu' => $data,
					),
					'where' => array(
						'level_access_menu' => $level
					)
				);
			}
			elseif ($level == 'user') {
				$act = array(
					'in' => array(
						'id_menu' => $data,
					),
					'where' => array(
						'level_access_menu !=' => 'admin'
					)
				);
			}

			$check_menu = $this->main_menu_list_model->get_detail_data('count',NULL,$act);
			if ($check_menu == count($data)) {
				return TRUE;
			}
			elseif ($check_menu < count($data)) {
				return FALSE;
			}
		}
		elseif ($menu_type == 'sub-menu') {
			if ($move_sub == NULL) {
				$act = array(
					'in' => array(
						'id_sub_menu' => $data,
					),
				);

				if ($level == 'admin') {
					$ac = array(
						'where' => array(
							'id_parent_menu' => $parent,
							'level_access_menu' => $level,
						)
					);
					$act = array_merge($act,$ac);
				}
				else{
					$ac = array(
						'where' => array(
							'id_parent_menu' => $parent,
							'level_access_menu !=' => 'admin',
						)
					);
					$act = array_merge($act,$ac);
				}

				$check_menu = $this->sub_menu_list_model->get_detail_data('count',array('main_menu_list'),$act);
				if ($check_menu == count($data)) {
					return TRUE;
				}
				elseif ($check_menu < count($data)) {
					return FALSE;
				}
			}
			elseif ($move_sub == TRUE) {
				$list_menu = $this->main_menu_list_model->get_detail_data('get',NULL,NULL,array('id_menu' => $parent),TRUE,array('level_access_menu'));
				$sub_menu = $this->sub_menu_list_model->get_detail_data('get',array('main_menu_list'),NULL,array('id_sub_menu' => $menu),TRUE,array('level_access_menu'));
				if (@$list_menu->level_access_menu == @$sub_menu->level_access_menu) {
					return TRUE;
				}
				else{
					return FALSE;
				}

				/*if ($level == 'admin') {
					$where = array('id_menu' => $parent, 'level_access_menu' => 'admin');
				}
				else{
					$where = array('id_menu' => $parent, 'level_access_menu !=' => 'admin');
				}
				$check_menu = $this->main_menu_list_model->get_detail_data('count',NULL,NULL,$where);
				if ($check_menu > 0) {
					return TRUE;
				}
				else{
					return FALSE;
				}*/
			}
		}
	}

	function check_menu($in_menu){
		if ($in_menu != '') {
			$list_menu = $this->main_menu_list_model->count(array('id_menu' => $in_menu));
			if ($list_menu > 0) {
				return TRUE;
			}
			else{
				$this->form_validation->set_message('check_menu', 'Maaf, menu yang anda pilih tidak ada dalam database');
				return FALSE;
			}
		}
	}

}
