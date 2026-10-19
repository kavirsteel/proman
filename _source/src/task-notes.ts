export function taskNotes(task:any,logs:any[]=[]):any[]{
 if(Array.isArray(task.notes))return task.notes;
 if(!task.description)return [];
 const created=[...logs].reverse().find((l:any)=>l.entity===task.id);
 return [{id:'initial-'+task.id,text:task.description,deadline:task.deadline||'',actor:created?.actor||'ثبت اولیه',at:created?.at||null}];
}
export function appendTaskNote(task:any,logs:any[],text:string,deadline:string,actor:string,at:string,id:string){
 return {...task,notes:[...taskNotes(task,logs),{id,text,deadline,actor,at}]};
}
