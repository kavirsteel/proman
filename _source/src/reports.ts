export function isOverdue(deadline:string,status:string,today:string){return Boolean(deadline&&deadline<today&&status!=='done');}
export function buildReport(projects:any[],tasks:any[],users:any[],today:string){
 const completed=tasks.filter(t=>t.status==='done').length;
 const overdue=tasks.filter(t=>isOverdue(t.deadline,t.status,today));
 const upcoming=tasks.filter(t=>t.deadline&&t.deadline>=today&&t.status!=='done').sort((a,b)=>a.deadline.localeCompare(b.deadline)).slice(0,5);
 const projectRows=projects.map(p=>{const rows=tasks.filter(t=>t.project===p.id),done=rows.filter(t=>t.status==='done').length;return {...p,total:rows.length,done,percent:rows.length?Math.round(done/rows.length*100):0,late:rows.filter(t=>isOverdue(t.deadline,t.status,today)).length};});
 const workload=users.map(u=>{const rows=tasks.filter(t=>t.assignee===u.email);return {...u,total:rows.length,open:rows.filter(t=>t.status!=='done').length,done:rows.filter(t=>t.status==='done').length,late:rows.filter(t=>isOverdue(t.deadline,t.status,today)).length};}).filter(u=>u.total>0).sort((a,b)=>b.open-a.open);
 return {completed,percent:tasks.length?Math.round(completed/tasks.length*100):0,overdue,upcoming,projectRows,workload,unassigned:tasks.filter(t=>!t.assignee&&t.status!=='done').length,statusCounts:Object.fromEntries(['todo','doing','review','done','blocked'].map(s=>[s,tasks.filter(t=>t.status===s).length]))};
}
