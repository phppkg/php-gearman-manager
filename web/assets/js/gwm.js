/**
 * Created by inhere on 2017/5/22.
 */

// 务必在加载 Vue 之后，立即同步设置以下内容
Vue.config.devtools = true

// config the axios
// Add a request interceptor
axios.interceptors.request.use(function (config) {
    // Do something before request is sent
    vm.progress = 1
    return config;
  }, function (error) {
    // Do something with request error
    return Promise.reject(error);
  });

// Add a response interceptor
axios.interceptors.response.use(function (response) {
    // Do something with response data
    vm.progress = 100
    setTimeout(function() {
      vm.progress = 0
    }, 1000)
    return response;
  }, function (error) {
    // Do something with response error
    return Promise.reject(error);
  });

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
    progress: 0,
    showAlert: false,
    alertText: '',
  },
  created () {
    console.log('VM created')
    // 第一次显示进度条 是加载页面
    this.$router.beforeEach((to, from, next) => {
      this.progress = 1
      next()
    })
    this.$router.afterEach(() => {
      this.progress = 100
      setTimeout(function() {
        this.progress = 0
      }.bind(this), 1000)
    })
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
      if (msg) {
        this.showAlert = true
        this.alertText = msg
      } else {
        this.showAlert = false
        this.alertText = ''
      }
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
