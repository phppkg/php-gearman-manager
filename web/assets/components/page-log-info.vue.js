components.pageLogInfo = {
  template: `
<div class="row">
  <div class="col-12">
    <form>
      <div class="form-group row">
        <label for="select-date" class="col-2 col-form-label">Date</label>
        <div class="col-10">
          <b-form-input v-model="selectDate" placeholder="select a date" id="select-date" autocompleted="on"></b-form-input>
        </div>
      </div>
      <div class="form-group">
        <button type="button" class="btn btn-primary push-md-4" @click="fetch"> Fetch Data </button>
      </div>
    </form>

    <!-- Simple -->
    <b-card class="mb-2" variant="success" v-if="jobsInfo.length">
      Date {{ selectDate }}, Worker (re)start times of the day: <code>{{ startTimes }}</code>,
      Job execute count:
        started - <code>{{ typeCounts.started }}</code>
        completed - <code>{{ typeCounts.completed }}</code>
        failed - <code>{{ typeCounts.failed }}</code>
    </b-card>

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
        <template slot="exec_count" scope="item">
          {{item.value}}
        </template>
        <template slot="actions" scope="item">
          <b-btn size="sm" @click="details(item)">Details</b-btn>
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
    </div>
  </div>
</div>
`,
  mounted() {
    flatpickr(document.getElementById('select-date'), {
      maxDate: "today"
    });

  },
  data: function () {
    return {
      selectDate: '',
      startTimes: 0,
      typeCounts: null,
      jobsInfo: [],
      infoFields: { // time role pid level job_name job_id exec_count
        time: {label: "Start Time"},
        role: {label: "Role", sortable: true },
        pid: {label: "PID" },
        level: {label: "Level", sortable: true },
        job_name: {label: "Job Name", sortable: true },
        job_id: {label: "Job ID", sortable: true },
        exec_count: {label: "Exec Job Count"},
        actions: {label: 'Actions'}
      },
      jobDetail: null,
      detailFields: {
        handler: {label: "Job Handler", sortable: true },
        end_time: {label: "End Time", sortable: true },
        status: {label: "Status", sortable: true },
        workload: {label: "Workload"}
      },
      curPage: 1,
      perPage: 10,
      filter: null
    }
  },
  methods: {
    fetch() {
      const self = this

      vm.alert()
      axios.get('/?r=log-info',{
        params: {
          date: this.selectDate
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
      })
        .catch(err => {
          console.error(err)
          vm.alert('network error!')
      })
    },
    fetchDetail(jobId) {
      const self = this

      vm.alert()
      axios.get('/?r=log-info',{
        params: {
          date: this.selectDate,
          jobId: jobId,
        }
      })
        .then(({data, status}) => {
          console.log(data)

          if (data.code !== 0) {
            vm.alert(data.msg ? data.msg : 'network error!')
            return
          }

          self.jobDetail = data.data.detail
      })
        .catch(err => {
          console.error(err)
          vm.alert('network error!')
      })
    }
  }
}
