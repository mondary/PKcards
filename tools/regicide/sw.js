const CACHE='regicide-v3'
const SHELL=['./','index.html','index2.html','manifest.json','icon192.png','icon512.png','card-reference.css']
self.addEventListener('install',()=>self.skipWaiting())
self.addEventListener('activate',e=>{
  e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))
})
self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET')return
  e.respondWith(
    caches.match(e.request).then(hit=>{
      const fetchP=fetch(e.request).then(res=>{
        if(res.ok&&new URL(e.request.url).origin===location.origin){const cl=res.clone();caches.open(CACHE).then(c=>c.put(e.request,cl))}
        return res
      }).catch(()=>hit)
      return hit||fetchP
    })
  )
})
