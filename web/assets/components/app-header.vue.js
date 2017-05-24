components.appHeader = {
  props: ['projInfo'],
  template: `
<header>
  <b-navbar toggleable type="inverse" variant="success">
    <div class="container">
      <b-nav-toggle target="nav_collapse"></b-nav-toggle>

      <b-link class="navbar-brand" to="/">
        <span>Php-gwm</span>
      </b-link>

      <b-collapse is-nav id="nav_collapse">

        <b-nav is-nav-bar>
          <b-nav-item to="/home">Home</b-nav-item>
          <b-nav-item to="/server-info">Server Info</b-nav-item>
          <b-nav-item to="/log-info">Log Parse</b-nav-item>
        </b-nav>

        <b-nav is-nav-bar class="ml-auto">
          <b-nav-item href="http">Github</b-nav-item>
          <b-nav-item href="http">Git@OSC</b-nav-item>
        </b-nav>
      </b-collapse>
    </div>
  </b-navbar>
</header>
`
}
