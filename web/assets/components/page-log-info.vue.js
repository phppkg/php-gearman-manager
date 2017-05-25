components.pageLogInfo = {
  template: `
<div class="row">
  <div class="col-12">
    <form>
      <div class="form-group row">
        <label for="select-date" class="col-3 col-form-label">Select A Date</label>
        <div class="col-6">
          <b-form-input v-model="selectDate" placeholder="select a date" id="select-date" autocompleted="on"></b-form-input>
        </div>
      </div>
      <div class="form-group row">
        <div class="col-6 push-sm-6">
          <b-form-checkbox v-model="cache">Cache</b-form-checkbox>
          <button class="btn btn-primary" type="button" @click="fetch"> Fetch Data </button>
        </div>
      </div>
    </form>

    <hr>
    <h4>Job information of the date: <span v-show="selectDate" class="text-success">{{ selectDate }}</span> <hr></h4>

    <!-- Simple -->
    <b-card class="mb-2" variant="success" v-if="jobsInfo.length">
      Worker (re)start times of the day: <code>{{ startTimes }}</code>,
      Job execute count:
        started - <code>{{ typeCounts.started }}</code>
        completed - <code>{{ typeCounts.completed }}</code>
        failed - <code>{{ typeCounts.failed }}</code>
    </b-card>

    <hr v-show="jobsInfo.length">

    <div>
      <div class="justify-content-center my-1 row">
        <b-form-fieldset horizontal label="Filter" class="col-6" :label-size="2">
          <b-form-input v-model="filter" placeholder="Type to Search"></b-form-input>
        </b-form-fieldset>
      </div>

      <b-table bordered hover show-empty
               head-variant="success"
               :items="jobsInfo"
               :fields="infoFields"
               :current-page="curPage"
               :per-page="perPage"
               :filter="filter"
      >
        <template slot="job_name" scope="item">
          <span class="badge badge-success">{{item.value}}</span>
        </template>
        <template slot="job_id" scope="item">
          <code>{{item.value}}</code>
        </template>
        <template slot="worker" scope="item">
          Executed job <code>{{item.item.exec_count}}</code>(PID<code>{{item.item.pid}}</code>)
        </template>
        <template slot="actions" scope="item">
          <b-btn size="sm" variant="outline-info" @click="showDetail(item)">Detail</b-btn>
        </template>
      </b-table>

      <div class="justify-content-center row my-1">
        <b-form-fieldset horizontal label="Rows per page" class="col-4" :label-size="7">
          <b-form-select :options="[{text:10,value:10},{text:15,value:15}]" v-model="perPage">
          </b-form-select>
        </b-form-fieldset>
        <b-form-fieldset horizontal label="Pagination" class="col-8" :label-size="2">
          <b-pagination size="sm" :total-rows="this.jobsInfo.length" :per-page="perPage" v-model="curPage"/>
        </b-form-fieldset>
      </div>

      <!-- Modal Component @shown="clearName" -->
      <b-modal ok-only id="d-modal" title="Job Detail" @change="changeModal">
        JobId: <code>{{jobDetail.id}}</code>
        <ul v-if="jobDetail">
          <li>Handler {{jobDetail.handler}}</li>
          <li>Handler {{jobDetail.handler}}</li>
        </ul>

      </b-modal>
    </div>

  </div>

</div>
`,
  mounted() {
    const el = document.getElementById('select-date')
    const fp = flatpickr(el, {
      defaultDate: "today",
      maxDate: "today"
    });

    this.selectDate = el.value
  },
  data: function () {
    return {
      cache: true,
      selectDate: '',
      startTimes: 0,
      typeCounts: null,
      jobsInfo: [],
      infoFields: { // time role pid level job_name job_id exec_count
        time: {label: "Start Time"},
        worker: {label: "Worker"},
        level: {label: "Log Level", sortable: true },
        job_name: {label: "Job Name", sortable: true },
        job_id: {label: "Job ID", sortable: true },
        actions: {label: 'Actions'}
      },
      jobDetail: null,
      detailFields: {
        handler: {label: "Job Handler", sortable: true },
        start_time: {label: "Start Time", sortable: true },
        end_time: {label: "End Time", sortable: true },
        status: {label: "Status", sortable: true },
        workload: {label: "Workload"}
      },
      curPage: 1,
      perPage: 10,
      filter: null
    }
  },
  computed: {
  },
  methods: {
    changeModal: function (isVisible, e) {
      console.log(isVisible, e)
    },
    showDetail: function (job) {
      console.log(job)
      this.fetchDetail(job)
      this.$root.$emit('show::modal', 'd-modal')
    },
    fetch() {
      const self = this
      const date = this.selectDate

      if (!date) {
        vm.alert('Please select a date!')
        return
      }

      if (this.cache && session.has(date)) {
        const data = session.getJson(date)

        this.startTimes = data.startTimes
        this.typeCounts = data.typeCounts
        this.jobsInfo = data.jobsInfo

        return
      }

      vm.alert()
      axios.get('/?r=jobs-info',{
        params: {
          date: date
        }
      })
        .then(({data, status}) => {
          console.log(data)

          if (data.code !== 0) {
            vm.alert(data.msg ? data.msg : 'network error!')
            return
          }

          self.startTimes = data.data.startTimes
          self.typeCounts = data.data.typeCounts
          self.jobsInfo = data.data.jobsInfo

          session.setJson(date, data.data)
      })
        .catch(err => {
          console.error(err)
          vm.alert('network error(catched)!')
      })
    },
    fetchDetail(job) {
      const self = this
      const jid = job.item.job_id

      if (session.has(jid)) {
        self.jobDetail = session.getJson(jid)

        return
      }

      vm.alert()
      axios.get('/?r=job-info',{
        params: {
          date: this.selectDate,
          jobId: jid
        }
      })
        .then(({data, status}) => {
          console.log(data)

          if (data.code !== 0) {
            vm.alert(data.msg ? data.msg : 'network error!')
            return
          }

          let detail = data.data
          detail.id = jid
          detail.start_time = job.item.time

          self.jobDetail = detail
          session.setJson(jid, detail)
      })
        .catch(err => {
          console.error(err)
          vm.alert('network error(catched)!')
      })
    }
  }
}
