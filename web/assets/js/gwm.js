/**
 * Created by inhere on 2017/5/22.
 */

// 务必在加载 Vue 之后，立即同步设置以下内容
Vue.config.devtools = true

const routes = [{
  path: '/home',
  component: components.pageHome
}, {
  path: '/server-info',
  component: components.pageServerInfo
}, {
  path: '/log-info',
  component: components.pageLogInfo
}, {
  path: '*',
  redirect: '/home'
}]

const router = new VueRouter({
  routes // （缩写）相当于 routes: routes
})

const vm = new Vue({
  router: router,
  components: {
    'app-header': components.appHeader,
    'app-footer': components.appFooter
  },
  data: {
    projInfo: {
      github: '',
      gitosc: '',
      version: ''
    },
    showAlert: false,
    alertText: '',
  },
  created () {
    console.log('VM created')
    this.fetch()
  },
  mounted () {
    console.log('VM mounted')
    // this.$nextTick(() => {
    //   this.fetch()
    // })
  },
  updated () {
    console.log('VM updated')
    // this.loadPlugin()
  },
  methods: {
    alert (msg) {
      this.showAlert = true
      this.alertText = msg
    },
    details(item) {
      alert(JSON.stringify(item))
    },
    fetch () {
      const self = this

      axios.get('/?r=proj-info')
      .then(({data, status}) => {
        console.log(data)
        self.projInfo = data.data
      }).catch(err => {
        console.error(err)
      })
    }
  }
}).$mount("#app")
