components.pageLogInfo = {
  template: `
<div class="row">
  <div class="col-12">
      <b-input-group left="Select date">
        <b-form-input v-model="selectDate" placeholder="select a date" id="select-date" autocompleted="on"></b-form-input>

        <!-- Attach Right button -->
        <b-input-group-button slot="right">
          <b-button size="" variant="outline-success" @click="fetch"> Fetch Data </b-button>
        </b-input-group-button>

      </b-input-group>
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
      jobsInfo: null,
      jobDetail: null
    }
  },
  methods: {
    fetch() {
      const self = this
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
