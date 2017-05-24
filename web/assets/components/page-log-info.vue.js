components.pageLogInfo = {
  template: `
<div class="row">
  <div class="col-12">
    welcome
  </div>
</div>
`,
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
