<template>
  <b-modal v-model="showModal" :title="title" size="xl" ok-only ok-variant="secondary" ok-title="Tutup">
    <b-table-simple bordered responsive v-if="rencana">
      <b-tr>
        <b-td>Paket UKK</b-td>
        <b-td>{{rencana.paket_ukk.nama_paket_id}}</b-td>
      </b-tr>
      <b-tr>
        <b-td>Penguji Internal</b-td>
        <b-td>{{rencana.guru_internal.nama_lengkap}}</b-td>
      </b-tr>
      <b-tr>
        <b-td>Penguji Eksternal</b-td>
        <b-td>{{rencana.guru_eksternal.nama_lengkap}}</b-td>
      </b-tr>
    </b-table-simple>
    <b-table-simple bordered responsive>
      <b-thead>
        <b-tr>
          <b-th class="text-center">No.</b-th>
          <b-th class="text-center">Nama Peserta Didik</b-th>
          <b-th class="text-center">NISN</b-th>
          <b-th class="text-center">Nilai</b-th>
          <b-th class="text-center">Kesimpulan</b-th>
          <b-th class="text-center">#</b-th>
        </b-tr>
      </b-thead>
      <b-tbody>
        <template v-for="(item, index) in data_siswa">
          <b-tr>
            <b-td class="text-center">{{index + 1}}</b-td>
            <b-td>{{item.nama}}</b-td>
            <b-td class="text-center">{{item.nisn}}</b-td>
            <b-td class="text-center">
              {{(item.nilai_ukk) ? item.nilai_ukk.nilai : '-'}}
            </b-td>
            <b-td class="text-center">{{kesimpulan_ukk(item)}}</b-td>
            <b-td class="text-center">
              <b-button variant="success" size="sm" :href="generate_link(item)" target="_blank" v-if="generate_link(item)">Cetak</b-button>
            </b-td>
          </b-tr>
        </template>
      </b-tbody>
    </b-table-simple>
  </b-modal>
</template>

<script>
import { BTableSimple, BThead, BTbody, BTh, BTr, BTd, BButton } from 'bootstrap-vue'
import eventBus from '@core/utils/eventBus'
export default {
  components: {
    BTableSimple, BThead, BTbody, BTh, BTr, BTd, BButton
  },
  /*props: {
    rencana: {
      type: Object,
      required: true
    },
    data_siswa: {
      type: Array,
      required: true
    },
  },*/
  data() {
    return {
      showModal: false,
      title: 'Detil Rencana Penilaian UKK',
      rencana: null,
      data_siswa: [],
      loading_guru: false,
    }
  },
  created() {
    eventBus.$on('open-modal-detil-rencana-ukk', this.handleEvent);
  },
  methods: {
    handleEvent(data){
      this.showModal = true
      this.rencana = data.rencana
      this.data_siswa = data.data_siswa
    },
    kesimpulan_ukk(item){
      var predikat = ''
      if(item.nilai_ukk && item.nilai_ukk.nilai){
        var nilai = item.nilai_ukk.nilai
        if (nilai >= 91 && nilai <= 100) {
          // 91-100 sangat kompeten
          predikat = 'Sangat Kompeten';
        } else if (nilai >= 81 && nilai <= 90) {
          // 81-90 kompeten
          predikat = 'Kompeten';
        } else if (nilai >= 70 && nilai <= 80) {
          // 70-80 cukup kompeten 
          predikat = 'Cukup Kompeten';
        } else if (nilai <= 69) {
          // 0-69 Belum kompeten
            predikat = 'Belum Kompeten';
        }
      }
      return predikat;
    },
    generate_link(item){
      var link_cetak = null
      if(item.nilai_ukk.nilai){
        link_cetak = `/cetak/sertifikat/${item.nilai_ukk.anggota_rombel_id}/${item.nilai_ukk.rencana_ukk_id}`
      }
      return link_cetak
    },
  },
}
</script>