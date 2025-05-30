<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Rapor_pts;
use App\Models\Rombongan_belajar;
use App\Models\Anggota_rombel;
use App\Models\Peserta_didik;
use App\Models\Rombel_empat_tahun;
use App\Models\Rencana_budaya_kerja;
use App\Models\Opsi_budaya_kerja;
use App\Models\Budaya_kerja;
use App\Models\Semester;
use App\Models\Nilai_ekstrakurikuler;
use App\Models\Rencana_ukk;
use App\Models\Nilai_ukk;
use App\Models\Paket_ukk;
use App\Models\Guru;
use App\Models\Sekolah;
use App\Models\Prakerin;
use App\Models\Absensi;
use Carbon\Carbon;
use PDF;
use Storage;

class CetakController extends Controller
{
    public function generate_pdf()
    {
        $data = [
        'foo' => 'bar'
        ];
        $pdf = PDF::loadView('cetak.document', $data);
        $pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=1;
		$pdf->getMpdf()->SetFooter('Nama Siswa - Nama Kelas |{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
        return $pdf->stream('document.pdf');
    }
	public function rapor_tengah_semester(){
		$tanggal_rapor = get_setting('tanggal_rapor_pts', request()->route('sekolah_id'), request()->route('semester_id'));
		$pd = Peserta_didik::withWhereHas('anggota_rombel', function($query){
			$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
			$query->with([
				'rombongan_belajar' => function($query){
					$query->with([
						'pembelajaran' => function($query){
							$query->orderBy('kelompok_id');
							$query->orderBy('no_urut');
							$query->whereNotNull('kelompok_id');
							$query->whereNotNull('no_urut');
							$query->with([
								'kelompok',
								'single_nilai_pts' => function($query){
									$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
								}
							]);
						},
						'jurusan',
						'kurikulum',
						'sekolah.kasek' => function($query){
							$query->where('semester_id', request()->route('semester_id'));
						},
					]);
				},
			]);
		})->first();
		$get_pembelajaran = [];
		foreach($pd->anggota_rombel->rombongan_belajar->pembelajaran as $pembelajaran){
			if(in_array($pembelajaran->mata_pelajaran_id, mapel_agama())){
				if(filter_pembelajaran_agama($pd->agama->nama, $pembelajaran->nama_mata_pelajaran)){
					$get_pembelajaran[$pembelajaran->pembelajaran_id] = $pembelajaran;
				}
			} else {
				$get_pembelajaran[$pembelajaran->pembelajaran_id] = $pembelajaran;
			}
		}
		$pembelajaran = [];
		$i=1;
		foreach($get_pembelajaran as $item){
			$pembelajaran[$item->kelompok->nama_kelompok][] = [
				'no' => $i++,
				'nama'	=> $item->nama_mata_pelajaran,
				'nilai'	=> $item->single_nilai_pts?->nilai,
				'deskripsi' => $item->single_nilai_pts?->deskripsi,
			];
		}
		$data = [
			'pd' => $pd,
			'pembelajaran' => $pembelajaran,
			'tanggal_rapor' => $tanggal_rapor ? Carbon::parse($tanggal_rapor)->translatedFormat('d F Y') : Carbon::now()->translatedFormat('d F Y'),
			'semester_id' => request()->route('semester_id'),
		];
		$pdf = PDF::loadView('cetak.rapor_nilai_pts', $data, [], [
			'format' => 'A4',
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 15,
			'margin_bottom' => 15,
			'margin_header' => 5,
			'margin_footer' => 5,
		]);
		$pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=0;
		$general_title = $pd->nama.' - '.$pd->anggota_rombel->rombongan_belajar->nama;
		$pdf->getMpdf()->SetFooter($general_title.'|{PAGENO}|Dicetak dari '.config('site.app_name').' v.'.get_setting('app_version'));
		/*$pdf = PDF::loadView('cetak.blank');
		$pdf->getMpdf()->AddPage('P','','','','',5,5,5,5,5,5,'', 'A4');
		$rapor_nilai = view('cetak.rapor_nilai_pts', $data);
		$pdf->getMpdf()->WriteHTML($rapor_nilai);*/
		$filename = 'Rapor_PTS_'.str_replace(' ','_', Str::of($pd->anggota_rombel->rombongan_belajar->nama)->ascii()).'_TA_'.request()->route('semester_id');
		return $pdf->stream($filename.'.pdf'); 
	}
    public function rapor_uts(Request $request){
        $rombongan_belajar_id = $request->route('rombongan_belajar_id');
        $callback = function($query){
			$query->with('nilai');
		};
		$rombongan_belajar = Rombongan_belajar::with('wali_kelas')->with(['anggota_rombel' => function($query){
			$query->with(['single_catatan_wali', 'peserta_didik.agama']);
		}])->with(['pembelajaran' => function($query) use ($callback){
			$query->with('kelompok')->orderBy('kelompok_id', 'asc')->orderBy('no_urut', 'asc');
			$query->whereHas('rapor_pts', $callback)->with(['rapor_pts'=> $callback]);
		}])->with('jurusan')->with('kurikulum')->with(['sekolah' => function($query){
			$query->with(['kasek' => function($query){
				$query->where('semester_id', semester_id());
			}]);
		}])->find($rombongan_belajar_id);
        $kur = 2017;
		$pdf = PDF::loadView('cetak.blank');
		$pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=0;
		$data['rombongan_belajar'] = $rombongan_belajar;
		$tanggal_rapor = get_setting('tanggal_rapor_pts', request()->route('sekolah_id'), request()->route('semester_id'));
		if($tanggal_rapor) {
            $data['tanggal_rapor'] = Carbon::parse($tanggal_rapor)->translatedFormat('d F Y');
        } else {
            $data['tanggal_rapor'] = Carbon::now()->translatedFormat('d F Y');
        }
        //$tanggal_rapor;
		foreach($rombongan_belajar->anggota_rombel as $anggota_rombel){
			$pdf->getMpdf()->SetFooter(strtoupper($anggota_rombel->peserta_didik->nama).' - '.$rombongan_belajar->nama.'|{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
			$data['peserta_didik'] = $anggota_rombel->peserta_didik;
			$data['anggota_rombel'] = $anggota_rombel;
			$data['sekolah'] = $rombongan_belajar->sekolah;
			$rapor_cover = view('cetak.pts.cover', $data);
			$pdf->getMpdf()->WriteHTML($rapor_cover);
			$get_pembelajaran=[];
			foreach($rombongan_belajar->pembelajaran as $pembelajaran){
                //$get_pembelajaran[$pembelajaran->pembelajaran_id] = $pembelajaran;
				if(in_array($pembelajaran->mata_pelajaran_id, mapel_agama())){
					if(filter_pembelajaran_agama($anggota_rombel->peserta_didik->agama->nama, $pembelajaran->nama_mata_pelajaran)){
						$get_pembelajaran[$pembelajaran->pembelajaran_id] = $pembelajaran;
					}
				} else {
					$get_pembelajaran[$pembelajaran->pembelajaran_id] = $pembelajaran;
				}
			}
			if($get_pembelajaran){
				foreach($get_pembelajaran as $pembelajaran){
					$rasio_p = ($pembelajaran->rasio_p) ? $pembelajaran->rasio_p : 50;
					foreach($pembelajaran->rapor_pts as $rapor_pts){
						$nilai[$pembelajaran->pembelajaran_id][$anggota_rombel->peserta_didik_id][] = $rapor_pts->nilai()->where('anggota_rombel_id', $anggota_rombel->anggota_rombel_id)->avg('nilai');
					}
					if(count($pembelajaran->rapor_pts) > 1){
						$nilai_siswa = array_sum($nilai[$pembelajaran->pembelajaran_id][$anggota_rombel->peserta_didik_id]) / count($nilai[$pembelajaran->pembelajaran_id][$anggota_rombel->peserta_didik_id]);
					} else {
						$nilai_siswa = array_sum($nilai[$pembelajaran->pembelajaran_id][$anggota_rombel->peserta_didik_id]);
					}
					$all_nilai[$pembelajaran->kelompok->nama_kelompok][$anggota_rombel->peserta_didik_id][] = array(
						'nama_mata_pelajaran'	=> $pembelajaran->nama_mata_pelajaran,
						'kkm'	=> $pembelajaran->skm,
						'angka'	=> number_format($nilai_siswa,0),
						'terbilang' => terbilang(number_format($nilai_siswa,0)),
					);
				}
			} else {
				$all_nilai[$pembelajaran->kelompok->nama_kelompok][$anggota_rombel->peserta_didik_id][] = [];
			}
			$data['all_nilai'] = $all_nilai;
			$pdf->getMpdf()->AddPage('P','','','','',5,5,5,5,5,5,'', 'A4');
			$rapor_nilai = view('cetak.pts.rapor_nilai_'.$kur, $data);
			$pdf->getMpdf()->WriteHTML($rapor_nilai);
			$pdf->getMpdf()->AddPage('P','','1','','',10,10,10,10,5,5,'', 'A4');
		}
		$filename = 'Rapor_PTS_'.str_replace(' ','_', Str::of($rombongan_belajar->nama)->ascii()).'_TA_'.request()->route('semester_id');
		return $pdf->stream($filename.'.pdf');  
    }
	public function rapor_cover(Request $request){
		//header("Content-Type: application/pdf");
		if($request->route('rombongan_belajar_id')){
		} else {
			$get_siswa = Anggota_rombel::with(['peserta_didik' => function($query){
				$query->with(['agama', 'pekerjaan_ayah', 'pekerjaan_ibu', 'pekerjaan_wali', 'wilayah', 'sekolah' => function($query){
					$query->with(['kasek' => function($query){
						$query->where('semester_id', semester_id());
					}]);
				}]);
			}])->with(['rombongan_belajar' => function($query){
				$query->with([
					'pembelajaran' => function($query){
						$query->with('kelompok');
						$query->with('nilai_akhir_pengetahuan');
						$query->with('nilai_akhir_keterampilan');
						$query->whereNotNull('kelompok_id');
						$query->orderBy('kelompok_id', 'asc');
						$query->orderBy('no_urut', 'asc');
					},
					'semester',
					'jurusan',
					'kurikulum'
				]);
			}])->find($request->route('anggota_rombel_id'));
			$logo_sekolah = ($get_siswa->rombongan_belajar->sekolah && $get_siswa->rombongan_belajar->sekolah->logo_sekolah)
				? public_path('./storage'.config('erapor.storage').'/images/'.$get_siswa->rombongan_belajar->sekolah->logo_sekolah)
				: public_path('./images/tutwuri.png');
			$params = array(
				'get_siswa'	=> $get_siswa,
			);
			$pdf = PDF::loadView('cetak.blank', $params, [], [
				'format' => 'A4',
				'margin_left' => 15,
				'margin_right' => 15,
				'margin_top' => 15,
				'margin_bottom' => 15,
				'margin_header' => 5,
				'margin_footer' => 5,
			]);
			$pdf->getMpdf()->defaultfooterfontsize=7;
			$pdf->getMpdf()->defaultfooterline=0;
			$general_title = $get_siswa->peserta_didik->nama.' - '.$get_siswa->rombongan_belajar->nama;
			$pdf->getMpdf()->SetFooter($general_title.'|{PAGENO}|Dicetak dari '.config('site.app_name').' v.'.get_setting('app_version'));
			$rapor_top = view('cetak.rapor_top', $params);
			$identitas_sekolah = view('cetak.identitas_sekolah', $params);

			$nomor_induk = $get_siswa->peserta_didik->no_induk;
			$tahun_ajaran_id = substr($nomor_induk, 0, 4);
			$pas_foto = null;

			if (Storage::disk('s3')->exists($tahun_ajaran_id)) {
				$base_path = $tahun_ajaran_id;
				$filename = $tahun_ajaran_id.'/'.$nomor_induk;
				if (Storage::disk('s3')->exists($filename.'.JPG')) {
					$pas_foto = Storage::disk('s3')->url($filename.'.JPG');
				} else if (Storage::disk('s3')->exists($filename.'.jpg')) {
					$pas_foto = Storage::disk('s3')->url($filename.'.jpg');
				}
			}

			// Override school's logo
			if (Storage::disk('s3')->exists('neskar_logo.png')) {
				$logo_sekolah = Storage::disk('s3')->url('neskar_logo.png');
			}

			// Remove school's logo for 2022 and below
			$tahun_ajaran_prefix_raw = $get_siswa->peserta_didik->diterima ? Carbon::parse($get_siswa->peserta_didik->getAttributes()['diterima'])->format('Y') : '20'.substr($tahun_ajaran_id, 0, 2);
			$tahun_ajaran_prefix = (int) $tahun_ajaran_prefix_raw;
			if ($tahun_ajaran_prefix <= 2022) {
				$logo_sekolah = null;
			}

			$params['pas_foto'] = $pas_foto;
			$identitas_peserta_didik = view('cetak.identitas_peserta_didik', $params);
			$pdf->getMpdf()->WriteHTML($rapor_top);
			$pdf->getMpdf()->WriteHTML($identitas_sekolah);
			
			if ($logo_sekolah !== null) {
				$pdf->getMpdf()->SetWatermarkImage($logo_sekolah, 0.2, array(80, 80));
				$pdf->getMpdf()->showWatermarkImage = true;
			}

			$pdf->getMpdf()->WriteHTML('<pagebreak />');
			$pdf->getMpdf()->WriteHTML($identitas_peserta_didik);
			return $pdf->stream($general_title.'-IDENTITAS.pdf');
		}
	}
	public function rapor_nilai_akhir(Request $request){
		//header("Content-Type: application/pdf");
		$cari_tingkat_akhir = Rombongan_belajar::where('sekolah_id', request()->route('sekolah_id'))->where('semester_id', request()->route('semester_id'))->where('tingkat', 13)->first();
		$get_siswa = Anggota_rombel::with([
			'kehadiran',
			'peserta_didik' => function($query){
				$query->with(['agama', 'wilayah', 'pekerjaan_ayah', 'pekerjaan_ibu', 'pekerjaan_wali', 'sekolah' => function($query){
					$query->with(['kasek' => function($query){
						$query->where('semester_id', request()->route('semester_id'));
					}]);
				}]);
			},
			'rombongan_belajar' => function($query){
				$query->where('jenis_rombel', 1);
				$query->with([
					'pembelajaran' => function($query){
						$callback = function($query){
							$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
						};
						$query->with([
							'kelompok',
							//'nilai_akhir' => $callback,
							'nilai_akhir_pengetahuan' => $callback,
							//'nilai_akhir_keterampilan' => $callback,
							//'nilai_akhir_pk' => $callback,
							'nilai_akhir_kurmer' => $callback,
							//'deskripsi_mata_pelajaran' => $callback,
							'single_deskripsi_mata_pelajaran' => $callback,
						]);
						$query->whereNull('induk_pembelajaran_id');
						$query->whereNotNull('kelompok_id');
						$query->whereNotNull('no_urut');
						$query->orderBy('kelompok_id', 'asc');
						$query->orderBy('no_urut', 'asc');
					},
					'jurusan',
					'kurikulum',
					'wali_kelas'
				]);
			},
			'kenaikan',
			'all_prakerin',
			'single_catatan_wali',
			/*'all_nilai_ekskul' => function($query){
				$query->whereHas('ekstrakurikuler', function($query){
					$query->where('semester_id', request()->route('semester_id'));
				});
				$query->with(['ekstrakurikuler']);
			},*/
			'anggota_ekskul' => function($query){
				$query->withWhereHas('rombongan_belajar', function($query){
                    $query->where('sekolah_id', request()->route('sekolah_id'));
                    $query->where('semester_id', request()->route('semester_id'));
                    $query->where('jenis_rombel', 51);
                });
				$query->withWhereHas('single_nilai_ekstrakurikuler');
            },
		])->find($request->route('anggota_rombel_id'));
		$budaya_kerja = Budaya_kerja::with(['catatan_budaya_kerja' => function($query){
			$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
		}])->get();
		$find_anggota_rombel_pilihan = Anggota_rombel::where(function($query) use ($get_siswa){
			$query->whereHas('rombongan_belajar', function($query) use ($get_siswa){
				$query->where('jenis_rombel', 16);
				$query->where('sekolah_id', request()->route('sekolah_id'));
				$query->where('semester_id', request()->route('semester_id'));
				//$query->where('jurusan_id', $get_siswa->rombongan_belajar->jurusan_id);
			});
			$query->where('peserta_didik_id', $get_siswa->peserta_didik_id);
		})->with([
			'rombongan_belajar' => function($query) use ($get_siswa){
				$query->where('jenis_rombel', 16);
				$query->where('sekolah_id', request()->route('sekolah_id'));
				$query->where('semester_id', request()->route('semester_id'));
				$query->with([
					'pembelajaran' => function($query) use ($get_siswa){
						$callback = function($query) use ($get_siswa){
							$query->whereHas('anggota_rombel', function($query) use ($get_siswa){
								$query->where('peserta_didik_id', $get_siswa->peserta_didik_id);
								$query->whereHas('rombongan_belajar', function($query){
									$query->where('jenis_rombel', 16);
									$query->where('sekolah_id', request()->route('sekolah_id'));
									$query->where('semester_id', request()->route('semester_id'));
								});
							});
						};
						$query->with([
							'anggota_rombel' => function($query) use ($get_siswa){
								$query->where('peserta_didik_id', $get_siswa->peserta_didik_id);
							},
							'kelompok',
							'nilai_akhir' => $callback,
							'nilai_akhir_pengetahuan' => $callback,
							'nilai_akhir_keterampilan' => $callback,
							'nilai_akhir_pk' => $callback,
							'nilai_akhir_kurmer' => $callback,
							'deskripsi_mata_pelajaran' => $callback,
							'single_deskripsi_mata_pelajaran' => $callback,
						]);
						//$query->whereNull('induk_pembelajaran_id');
						$query->whereNotNull('kelompok_id');
						$query->whereNotNull('no_urut');
						$query->orderBy('kelompok_id', 'asc');
						$query->orderBy('no_urut', 'asc');
					},
				]);
			},
		])->get();
		$tanggal_rapor = get_setting('tanggal_rapor', request()->route('sekolah_id'), request()->route('semester_id'));
		if($get_siswa->rombongan_belajar->semester->semester == 2 && $get_siswa->rombongan_belajar->tingkat >= 12){
			$tanggal_rapor = get_setting('tanggal_rapor_kelas_akhir', request()->route('sekolah_id'), request()->route('semester_id'));
		}
		if($tanggal_rapor) {
            $tanggal_rapor = Carbon::parse($tanggal_rapor)->translatedFormat('d F Y');
        } else {
            $tanggal_rapor = Carbon::now()->translatedFormat('d F Y');
        }
		//$rombel_4_tahun = Rombel_empat_tahun::select('rombongan_belajar_id')->where('sekolah_id', request()->route('sekolah_id'))->where('semester_id', request()->route('semester_id'))->get()->keyBy('rombongan_belajar_id')->keys()->toArray();
		$rombel_4_tahun = Rombel_empat_tahun::with(['rombongan_belajar'])->where('sekolah_id', request()->route('sekolah_id'))->where('semester_id', request()->route('semester_id'))->get();
		$jurusan_sp_id = [];
		$opsi = 'naik';
		$rombel = $get_siswa->rombongan_belajar;
		if($rombel->tingkat >= 12 || $rombel->tingkat == 12 && !$rombel->rombel_empat_tahun){
            $opsi = 'lulus';
        }
        foreach($rombel_4_tahun as $r4){
            $jurusan_sp_id[] = $r4->rombongan_belajar->jurusan_sp_id;
        }
        if($rombel->tingkat == 12 && in_array($rombel->jurusan_sp_id, $jurusan_sp_id)){
            $opsi = 'naik';
        }
		$params = array(
			'budaya_kerja' => $budaya_kerja,
			'get_siswa'	=> $get_siswa,
			'tanggal_rapor'	=> $tanggal_rapor,
			'cari_tingkat_akhir'	=> $cari_tingkat_akhir,
			'rombel_4_tahun' => $rombel_4_tahun,
			'find_anggota_rombel_pilihan' => $find_anggota_rombel_pilihan,
			'opsi' => $opsi,
		);
		//return view('cetak.rapor_nilai', $params);
		//return view('cetak.rapor_catatan', $params);
		$pdf = PDF::loadView('cetak.blank', $params, [], [
			'mode' => '+aCJK',
			'autoScriptToLang' => true,
			'autoLangToFont' => true,
			'format' => 'A4',
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 15,
			'margin_bottom' => 15,
			'margin_header' => 5,
			'margin_footer' => 5,
		]);
		$pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=0;
		$general_title = $get_siswa->peserta_didik->nama;
		$general_title .= ' - ';
		$general_title .= $get_siswa->rombongan_belajar->nama;
		$pdf->getMpdf()->SetFooter($general_title.'|{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
		if(request()->route('semester_id') == 20212){
			$rapor_nilai = view('cetak.rapor_nilai.'.request()->route('semester_id'), $params);
		} else {
			$rapor_nilai = view('cetak.rapor_nilai_akhir', $params);
		}
		$pdf->getMpdf()->WriteHTML($rapor_nilai);
		$pdf->getMpdf()->WriteHTML('<pagebreak />');
		$rapor_catatan = view('cetak.rapor_catatan', $params);
		$pdf->getMpdf()->WriteHTML($rapor_catatan);
		$pdf->getMpdf()->allow_charset_conversion = true;
		return $pdf->stream('RAPOR '.$general_title.'.pdf');
	}
	public function rapor_semester(Request $request){
		if($request->route('rombongan_belajar_id')){
		} else {
			$cari_tingkat_akhir = Rombongan_belajar::where('sekolah_id', request()->route('sekolah_id'))->where('semester_id', request()->route('semester_id'))->where('tingkat', 13)->first();
			$get_siswa = Anggota_rombel::with([
				'peserta_didik' => function($query){
					$query->with(['agama', 'wilayah', 'pekerjaan_ayah', 'pekerjaan_ibu', 'pekerjaan_wali', 'sekolah' => function($query){
						$query->with(['kasek' => function($query){
							$query->where('semester_id', request()->route('semester_id'));
						}]);
					}]);
				},
				'rombongan_belajar' => function($query){
					$query->where('jenis_rombel', 1);
					$query->with([
						'pembelajaran' => function($query){
							$callback = function($query){
								$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
							};
							$query->with([
								'kelompok',
								'nilai_akhir_pengetahuan' => $callback,
								'nilai_akhir_keterampilan' => $callback,
								'nilai_akhir_pk' => $callback,
								'deskripsi_mata_pelajaran' => $callback,
							]);
							$query->whereNotNull('kelompok_id');
							$query->orderBy('kelompok_id', 'asc');
							$query->orderBy('no_urut', 'asc');
						},
						'jurusan',
						'kurikulum',
						'wali_kelas'
					]);
				},
				'single_catatan_ppk' => function($query){
					$query->with(['nilai_karakter' => function($query){
						$query->with('sikap');
					}]);
				},
				'kenaikan', 
				/*'all_nilai_ekskul' => function($query){
					$query->whereHas('ekstrakurikuler', function($query){
						$query->where('semester_id', request()->route('semester_id'));
					});
					$query->with(['ekstrakurikuler']);
				},*/
				'kehadiran',
				'all_prakerin',
				'single_catatan_wali',
				'anggota_ekskul' => function($query){
					$query->withWhereHas('rombongan_belajar', function($query){
						$query->where('sekolah_id', request()->route('sekolah_id'));
						$query->where('semester_id', request()->route('semester_id'));
						$query->where('jenis_rombel', 51);
					});
					$query->withWhereHas('single_nilai_ekstrakurikuler');
				},
			])->find($request->route('anggota_rombel_id'));
			//$rombel_4_tahun = Rombel_empat_tahun::select('rombongan_belajar_id')->where('sekolah_id', request()->route('sekolah_id'))->where('semester_id', request()->route('semester_id'))->get()->keyBy('rombongan_belajar_id')->keys()->toArray();
			$rombel_4_tahun = Rombel_empat_tahun::with(['rombongan_belajar'])->where('sekolah_id', $get_siswa->sekolah_id)->where('semester_id', request()->route('semester_id'))->get();
			$jurusan_sp_id = [];
			foreach($rombel_4_tahun as $r4){
				$jurusan_sp_id[] = $r4->rombongan_belajar->jurusan_sp_id;
			}
			$tanggal_rapor = Carbon::now();
			if($get_siswa->kenaikan){
				if($get_siswa->kenaikan->status == 3){
					$tanggal_rapor = get_setting('tanggal_rapor_kelas_akhir', $get_siswa->sekolah_id, $get_siswa->semester_id);
				} else {
					$tanggal_rapor = get_setting('tanggal_rapor', $get_siswa->sekolah_id, $get_siswa->semester_id);
				}
			}
			$opsi = 'naik';
			if($get_siswa->rombongan_belajar->tingkat >= 12 || $get_siswa->rombongan_belajar->tingkat == 12 && !$get_siswa->rombongan_belajar->rombel_empat_tahun){
				$opsi = 'lulus';
			}
			if($get_siswa->rombongan_belajar->tingkat == 12 && in_array($get_siswa->rombongan_belajar->jurusan_sp_id, $jurusan_sp_id)){
				$opsi = 'naik';
			}
			$params = array(
				'get_siswa'	=> $get_siswa,
				'tanggal_rapor' => Carbon::parse($tanggal_rapor)->translatedFormat('d F Y'),
				'cari_tingkat_akhir'	=> $cari_tingkat_akhir,
				'rombel_4_tahun' => $rombel_4_tahun,
				'opsi' => $opsi,
			);
			if(!$get_siswa->peserta_didik){
				return view('cetak.no_pd');
			}
			$pdf = PDF::loadView('cetak.blank', $params, [], [
				'format' => 'A4',
				'margin_left' => 15,
				'margin_right' => 15,
				'margin_top' => 15,
				'margin_bottom' => 15,
				'margin_header' => 5,
				'margin_footer' => 5,
			]);
			$pdf->getMpdf()->defaultfooterfontsize=7;
			$pdf->getMpdf()->defaultfooterline=0;
			$general_title = $get_siswa->peserta_didik->nama.' - '.$get_siswa->rombongan_belajar->nama;
			$pdf->getMpdf()->SetFooter($general_title.'|{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
			$rapor_nilai = view('cetak.rapor_nilai', $params);
			//dd($params);
			$pdf->getMpdf()->WriteHTML($rapor_nilai);
			if (strpos($get_siswa->rombongan_belajar->kurikulum->nama_kurikulum, 'Pusat') == false){
				$pdf->getMpdf()->WriteHTML('<pagebreak />');
				$rapor_catatan = view('cetak.rapor_catatan', $params);
				$pdf->getMpdf()->WriteHTML($rapor_catatan);
				$rapor_karakter = view('cetak.rapor_karakter', $params);
				$pdf->getMpdf()->WriteHTML('<pagebreak />');
				$pdf->getMpdf()->WriteHTML($rapor_karakter);
			}
			return $pdf->stream($general_title.'-NILAI.pdf');
		}
	}
	public function rapor_pendukung($query, $id){
		if($query){
			$get_siswa = Anggota_rombel::with('peserta_didik')->with('sekolah')->with('prestasi')->find($id);
			$params = array(
				'get_siswa'	=> $get_siswa,
			);
			$pdf = PDF::loadView('cetak.blank', $params, [], [
				'format' => 'A4',
				'margin_left' => 15,
				'margin_right' => 15,
				'margin_top' => 15,
				'margin_bottom' => 15,
				'margin_header' => 5,
				'margin_footer' => 5,
			]);
			$pdf->getMpdf()->defaultfooterfontsize=7;
			$pdf->getMpdf()->defaultfooterline=0;
			$general_title = strtoupper($get_siswa->siswa->nama).' - '.$get_siswa->rombongan_belajar->nama;
			$pdf->getMpdf()->SetFooter($general_title.'| |Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
			$rapor_pendukung = view('cetak.rapor_pendukung', $params);
			$pdf->getMpdf()->WriteHTML($rapor_pendukung);
			return $pdf->stream($general_title.'-LAMPIRAN.pdf');
		} else {
			//$id = rombongan_belajar_id
		}
		$pdf = PDF::loadView('cetak.perbaikan');
		return $pdf->stream('document.pdf');
	}
	public function rapor_p5($anggota_rombel_id){
		$get_siswa = Anggota_rombel::with([
			'peserta_didik', 
			'nilai_budaya_kerja',
			'rombongan_belajar.sekolah',
		])->find($anggota_rombel_id);
		$params = array(
			'semester' => Semester::find(request()->route('semester_id')),
			'get_siswa'	=> $get_siswa,
			'rencana_budaya_kerja' => Rencana_budaya_kerja::withWhereHas('pembelajaran', function($query){
				$query->has('induk');
			})->where('rombongan_belajar_id', $get_siswa->rombongan_belajar_id)
			->with([
				'aspek_budaya_kerja' => function($query) use ($anggota_rombel_id){
					$query->with([
						'elemen_budaya_kerja' => function($query) use ($anggota_rombel_id){
							$query->with(['nilai_budaya_kerja' => function($query) use ($anggota_rombel_id){
								$query->where('anggota_rombel_id', $anggota_rombel_id);
								$query->whereNotNull('aspek_budaya_kerja_id');
							}]);
						},
						'budaya_kerja',
					]);
				},
				'catatan_budaya_kerja' => function($query) use ($anggota_rombel_id){
					$query->where('anggota_rombel_id', $anggota_rombel_id);
				},
			])->get(),
			'opsi_budaya_kerja' => Opsi_budaya_kerja::where('opsi_id', '<>', 1)->orderBy('updated_at', 'ASC')->get(),
			'budaya_kerja' => Budaya_kerja::orderBy('budaya_kerja_id')->get(),
		);
		$pdf = PDF::loadView('cetak.blank', $params, [], [
			'format' => 'A4',
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 15,
			'margin_bottom' => 15,
			'margin_header' => 5,
			'margin_footer' => 5,
		]);
		$pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=0;
		$general_title = strtoupper($get_siswa->peserta_didik->nama).' - '.$get_siswa->rombongan_belajar->nama;
		$pdf->getMpdf()->SetFooter($general_title.'|{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
		$rapor_p5bk = view('cetak.rapor_p5', $params);
		$pdf->getMpdf()->WriteHTML($rapor_p5bk);
		$pdf->getMpdf()->showImageErrors = true;
		return $pdf->stream($general_title.'-RAPOR-P5.pdf');
	}
	public function sertifikat($anggota_rombel_id, $rencana_ukk_id){
		$anggota_rombel = Anggota_rombel::with('peserta_didik')->find($anggota_rombel_id);
		$callback = function($query) use ($anggota_rombel_id){
			$query->where('anggota_rombel_id', $anggota_rombel_id);
		};
		$rencana_ukk = Rencana_ukk::with('guru_internal')->with(['guru_eksternal' => function($query){
			$query->with('dudi');
		}])->with(['nilai_ukk' => $callback])->find($rencana_ukk_id);
		$count_penilaian_ukk = Nilai_ukk::where('peserta_didik_id', $anggota_rombel->peserta_didik_id)->count();
		$data['siswa'] = $anggota_rombel;
		$data['sekolah_id'] = $anggota_rombel->sekolah_id;
		$data['rencana_ukk'] = $rencana_ukk;
		$data['count_penilaian_ukk'] = $count_penilaian_ukk;
		$data['paket'] = Paket_ukk::with('jurusan')->with(['unit_ukk' => function($query){
			$query->orderBy('kode_unit');
		}])->find($rencana_ukk->paket_ukk_id);
		$data['asesor'] = Guru::with('dudi')->find($rencana_ukk->eksternal);
		$data['sekolah'] = Sekolah::with(['kasek' => function($query) use ($anggota_rombel){
			$query->where('semester_id', $anggota_rombel->semester_id);
		}])->find($anggota_rombel->sekolah_id);
		$pdf = PDF::loadView('cetak.sertifikat1', $data);
		$pdf->getMpdf()->AddPage('P');
		$rapor_cover= view('cetak.sertifikat2', $data);
		$pdf->getMpdf()->WriteHTML($rapor_cover);
		$general_title = strtoupper($anggota_rombel->peserta_didik->nama);
		return $pdf->stream($general_title.'-SERTIFIKAT.pdf');
	}
	public function rapor_pelengkap(){
		$get_siswa = Peserta_didik::whereHas('anggota_rombel', function($query){
			$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
		})->with([
			'sekolah',
			'anggota_rombel' => function($query){
				$query->where('anggota_rombel_id', request()->route('anggota_rombel_id'));
				$query->with(['rombongan_belajar', 'prestasi']);
			}
		])->first();
		$params = array(
			'get_siswa'	=> $get_siswa,
		);
		$pdf = PDF::loadView('cetak.blank', $params, [], [
			'format' => 'A4',
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 15,
			'margin_bottom' => 15,
			'margin_header' => 5,
			'margin_footer' => 5,
		]);
		$pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=0;
		$general_title = strtoupper($get_siswa->nama).' - '.$get_siswa->anggota_rombel->rombongan_belajar->nama;
		$pdf->getMpdf()->SetFooter($general_title.'| |Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
		$rapor_pendukung = view('cetak.rapor_pendukung', $params);
		$pdf->getMpdf()->WriteHTML($rapor_pendukung);
		return $pdf->stream($general_title.'-LAMPIRAN.pdf');
	}
	public function rapor_pkl()
    {
		$pd = Peserta_didik::with([
			'sekolah' => function($query){
				$query->select('sekolah_id', 'nama', 'kabupaten');
			},
			'kelas' => function($query){
				$query->where('rombongan_belajar.semester_id', request()->route('semester_id'));
				$query->where('tingkat', '<>', 0);
				$query->where('jenis_rombel', 1);
			},
			'pd_pkl' => function($query){
				$query->withWhereHas('praktik_kerja_lapangan', function($query){
					$query->withWhereHas('akt_pd', function($query){
						$query->with('mou');
					});
					$query->where('pkl_id', request()->route('pkl_id'));
				});
			},
			'nilai_pkl' => function($query){
				$query->with(['tp']);
				$query->where('pkl_id', request()->route('pkl_id'));
			},
			'absensi_pkl' => function($query){
				$query->where('pkl_id', request()->route('pkl_id'));
			}
		])->find(request()->route('peserta_didik_id'));
        $data = [
        	'pd' => $pd,
        ];
		$pdf = PDF::loadView('cetak.rapor-pkl', $data);
        $pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=1;
		$pdf->getMpdf()->SetFooter($pd->nama.' - '. $pd->kelas->nama .' |{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
		$general_title = $pd->nama.' - '.$pd->pd_pkl->praktik_kerja_lapangan->dudi->nama_dudi.'-'.Carbon::parse($pd->pd_pkl->praktik_kerja_lapangan->tanggal_selesai)->format('d-m-Y');
		return $pdf->stream(clean($general_title).'.pdf');
        //return $pdf->stream('document.pdf');
    }
	public function buku_induk(){
		$rombongan_belajar = Rombongan_belajar::withWhereHas('single_anggota_rombel', function($query){
			$query->where('peserta_didik_id', request()->route('peserta_didik_id'));
			$query->with(['single_kenaikan_kelas']);
		})->with([
			'semester', 
			'pembelajaran' => function($query){
				$query->with(['kelompok']);
				$query->orderBy('kelompok_id');
				$query->orderBy('no_urut');
				$query->whereNotNull('kelompok_id');
				$query->whereNotNull('no_urut');
			},
			'kurikulum'
		])->where('jenis_rombel', 1)->orderBy('semester_id')->get();
		$pd = Peserta_didik::find(request()->route('peserta_didik_id'));
		$data = [
        	'pd' => $pd,
			'semester_id' => request()->route('semester_id'),
        ];
		$pdf = PDF::loadView('cetak.buku-induk.cover', $data);
        $pdf->getMpdf()->defaultfooterfontsize=7;
		$pdf->getMpdf()->defaultfooterline=1;
		$general_title = 'BUKU INDUK '.strtoupper($pd->nama);
		$pdf->getMpdf()->SetFooter($general_title .' |{PAGENO}|Dicetak dari '.config('app.name').' v.'.get_setting('app_version'));
		$pdf->getMpdf()->AddPage('P');
        $identitas = view('cetak.buku-induk.identitas', $data);
		$pdf->getMpdf()->WriteHTML($identitas);
		$rombel_4_tahun = Rombel_empat_tahun::with(['rombongan_belajar'])->where('sekolah_id', $pd->sekolah_id)->where('semester_id', request()->route('semester_id'))->get();
		$jurusan_sp_id = [];
		foreach($rombel_4_tahun as $r4){
			$jurusan_sp_id[] = $r4->rombongan_belajar->jurusan_sp_id;
		}
		foreach($rombongan_belajar as $rombel){
			$budaya_kerja = Budaya_kerja::with(['catatan_budaya_kerja' => function($query) use ($rombel){
				$query->whereHas('anggota_rombel', function($query) use ($rombel){
					$query->where('peserta_didik_id', request()->route('peserta_didik_id'));
					$query->where('semester_id', $rombel->semester_id);
				});
			}])->get();
			$prakerin = Prakerin::whereHas('anggota_rombel', function($query) use ($rombel){
				$query->where('peserta_didik_id', request()->route('peserta_didik_id'));
				$query->where('semester_id', $rombel->semester_id);
			})->get();
			$absensi = Absensi::whereHas('anggota_rombel', function($query) use ($rombel){
				$query->where('peserta_didik_id', request()->route('peserta_didik_id'));
				$query->where('semester_id', $rombel->semester_id);
			})->first();
			$tanggal_rapor = Carbon::now();
			if($rombel->single_anggota_rombel->single_kenaikan_kelas){
				if($rombel->single_anggota_rombel->single_kenaikan_kelas->status == 3){
					$tanggal_rapor = get_setting('tanggal_rapor_kelas_akhir', $rombel->sekolah_id, $rombel->semester_id);
				} else {
					$tanggal_rapor = get_setting('tanggal_rapor', $rombel->sekolah_id, $rombel->semester_id);
				}
			}
			$opsi = 'naik';
			if($rombel->tingkat >= 12 || $rombel->tingkat == 12 && !$rombel->rombel_empat_tahun){
				$opsi = 'lulus';
			}
			if($rombel->tingkat == 12 && in_array($rombel->jurusan_sp_id, $jurusan_sp_id)){
				$opsi = 'naik';
			}
			$all_nilai_ekskul = $pd->nilai_ekskul()->where(function($query) use ($rombel){
				$query->where('semester_id', $rombel->semester_id);
			})->get();
			$data = [
				'rombongan_belajar' => $rombel,
				'pd' => $pd,
				'budaya_kerja' => $budaya_kerja,
				'all_prakerin' => $prakerin,
				'all_nilai_ekskul' => $all_nilai_ekskul,
				'absensi' => $absensi,
				'tanggal_rapor' => Carbon::parse($tanggal_rapor)->translatedFormat('d F Y'),
				'kenaikan_kelas' => $rombel->single_anggota_rombel->single_kenaikan_kelas,
				'opsi' => $opsi,
			];
			$pdf->getMpdf()->AddPage('P');
			if(merdeka($rombel->kurikulum->nama_kurikulum)){
				$pdf->getMpdf()->WriteHTML(view('cetak.buku-induk.nilai-kurmer', $data));
			} else {
				if($rombel->semester->semester_id >= 20221){
					$pdf->getMpdf()->WriteHTML(view('cetak.buku-induk.nilai-new-kurtilas', $data));
				} else {
					$pdf->getMpdf()->WriteHTML(view('cetak.buku-induk.nilai-kurtilas', $data));
				}
			}
			$pdf->getMpdf()->AddPage('P');
			$pdf->getMpdf()->WriteHTML(view('cetak.buku-induk.catatan', $data));
		}
		$pdf->getMpdf()->AddPage('P');
		$pdf->getMpdf()->WriteHTML(view('cetak.buku-induk.lampiran', $data));
		return $pdf->stream($general_title.'-LAMPIRAN.pdf');
	}
}
